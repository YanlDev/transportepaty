<?php

use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Conductor;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AsignadorVueltas;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

const DIR_MINA = 'ASIENTO MINERO SAN RAFAEL Puno - ANTAUTA - MELGAR - PUNO';
const DIR_PISCO = 'CAR. PANAMERICANA SUR KM 245 - PARACAS - PISCO - ICA';
const DIR_CALLAO = 'AV. ELMER FAUCETT 3348 - CALLAO - PROV. CONST. DEL CALLAO - PROV. CONST. DEL CALLAO';
const DIR_HUARAL = 'PARCELA NRO 55 - HUARAL - HUARAL - LIMA';
const DIR_JULIACA = 'JR. 8 NOVIEMBRE 615 - JULIACA - SAN ROMAN - PUNO';

/**
 * Una GR registrada: queda en la bandeja hasta que alguien la asigna.
 */
function registrarGuia(Vehiculo $tracto, string $fecha, string $origen, string $destino, TipoCarga $carga): Viaje
{
    return Viaje::factory()->create([
        'tracto_id' => $tracto->id,
        'placa_tracto' => $tracto->placa,
        'fecha_traslado' => $fecha,
        'fecha_emision' => $fecha,
        'origen' => $origen,
        'destino' => $destino,
        'tipo_carga' => $carga,
    ])->fresh();
}

/**
 * La vuelta «concentrado · particular a Juliaca»: baja con concentrado y
 * vacía a Lima, y sube con particular a Juliaca.
 */
function programarVuelta(Vehiculo $tracto, string $salida): Vuelta
{
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $tracto->id, 'inicio' => $salida]);

    foreach ([
        [SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado],
        [SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Vacio],
        [SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Juliaca, TipoCarga::Particular],
    ] as $orden => [$sentido, $origen, $destino, $carga]) {
        ParadaVuelta::factory()->create([
            'vuelta_id' => $vuelta->id, 'sentido' => $sentido, 'orden' => $orden + 1,
            'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga,
            'lugar_origen' => null, 'lugar_destino' => null, 'cliente' => null, 'fecha' => null,
        ]);
    }

    return $vuelta;
}

function paradaDe(Vuelta $vuelta, int $orden): ParadaVuelta
{
    return $vuelta->paradas()->where('orden', $orden)->sole();
}

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('visor', 'web');
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $this->admin = actorConRol('admin');
});

it('never puts a new GR in a ticket on its own', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');

    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    expect($guia->parada_vuelta_id)->toBeNull()
        ->and($vuelta->fresh()->estado)->toBe(EstadoVuelta::Programada);
});

it('suggests the open ticket of the unit and the stop that matches', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-04', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    $asignador = app(AsignadorVueltas::class);

    expect($asignador->sugerencia($guia, $asignador->ticketsPara($guia)))->toBe([
        'vuelta_id' => $vuelta->id,
        'parada_id' => paradaDe($vuelta, 1)->id,
        'sentido' => 'ida',
    ]);
});

it('assigns the GR to the stop the person chose and the ticket leaves', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => paradaDe($vuelta, 1)->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    $guia->refresh();
    expect($guia->parada->orden)->toBe(1)
        ->and($guia->asignada_por)->toBe($this->admin->id)
        ->and($guia->asignada_at)->not->toBeNull()
        ->and($guia->parada->lugar_origen)->toBe('Antauta')
        ->and($guia->parada->fuera_de_programa)->toBeFalse()
        ->and($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);
});

it('adds what was not programmed as an unplanned stop at the end of the leg the person chose', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-07', DIR_PISCO, DIR_CALLAO, TipoCarga::Metalico);

    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'sentido' => 'ida'])
        ->assertSessionHasNoErrors();

    $parada = $guia->fresh()->parada;
    expect($parada->fuera_de_programa)->toBeTrue()
        ->and($parada->sentido)->toBe(SentidoTramo::Ida)
        ->and($parada->orden)->toBe(3)
        // El regreso se corrió para dejarle lugar.
        ->and($vuelta->paradas()->where('sentido', SentidoTramo::Regreso)->value('orden'))->toBe(4);
});

it('takes the load of the GR on a programmed stop that was waiting for it', function (): void {
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => '2026-11-05']);
    $subida = ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Ida, 'orden' => 1,
        'zona_origen' => ZonaRuta::Juliaca, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Materiales,
        'lugar_origen' => null, 'lugar_destino' => null, 'cliente' => null, 'fecha' => null,
    ]);
    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_JULIACA, DIR_MINA, TipoCarga::Particular);

    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => $subida->id])
        ->assertSessionHasNoErrors();

    expect($subida->fresh()->tipo_carga)->toBe(TipoCarga::Particular);
});

it('rejects a ticket of another unit, a closed one, a stop of another ticket and an empty run', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $otraUnidad = programarVuelta(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]), '2026-11-05');
    $cerrada = Vuelta::factory()->create(['tracto_id' => $this->tracto->id, 'estado' => EstadoVuelta::Cerrada]);
    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $otraUnidad->id, 'parada_vuelta_id' => paradaDe($otraUnidad, 1)->id])
        ->assertSessionHasErrors('vuelta_id');
    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $cerrada->id, 'sentido' => 'ida'])
        ->assertSessionHasErrors('vuelta_id');
    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => paradaDe($otraUnidad, 1)->id])
        ->assertSessionHasErrors('parada_vuelta_id');
    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => paradaDe($vuelta, 2)->id])
        ->assertSessionHasErrors('parada_vuelta_id');

    expect($guia->fresh()->parada_vuelta_id)->toBeNull();
});

it('takes the GR out of its ticket back to the tray', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-07', DIR_PISCO, DIR_CALLAO, TipoCarga::Metalico);
    actingAs($this->admin)->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'sentido' => 'ida']);

    actingAs($this->admin)
        ->delete(route('viajes.ticket.quitar', $guia))
        ->assertSessionHas('toast.type', 'success');

    // La parada no programada se va con su única GR, y el ticket vuelve a
    // «por salir».
    expect($guia->fresh()->parada_vuelta_id)->toBeNull()
        ->and($guia->fresh()->asignada_por)->toBeNull()
        ->and($vuelta->paradas()->count())->toBe(3)
        ->and($vuelta->fresh()->estado)->toBe(EstadoVuelta::Programada);
});

it('marks a GR without ticket with its reason and can bring it back', function (): void {
    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    actingAs($this->admin)
        ->post(route('viajes.sinTicket', $guia), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
    actingAs($this->admin)
        ->post(route('viajes.sinTicket', $guia), ['motivo' => 'Ya andaba en ruta antes del 05-11.'])
        ->assertSessionHas('toast.type', 'success');

    expect($guia->fresh()->sin_ticket_at)->not->toBeNull()
        ->and($guia->fresh()->sin_ticket_por)->toBe($this->admin->id);

    actingAs($this->admin)->delete(route('viajes.sinTicket.devolver', $guia));

    expect($guia->fresh()->sin_ticket_at)->toBeNull()
        ->and($guia->fresh()->motivo_sin_ticket)->toBeNull();
});

it('lists the tray with the open tickets of each unit and what the system suggests', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    $sinTicket = registrarGuia($this->tracto, '2026-11-05', DIR_HUARAL, DIR_JULIACA, TipoCarga::Particular);
    app(AsignadorVueltas::class)->marcarSinTicket($sinTicket, 'En ruta', $this->admin);

    actingAs($this->admin)
        ->get(route('vueltas.guias'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/guias')
            ->has('pendientes', 1)
            ->where('pendientes.0.id', $guia->id)
            ->where('pendientes.0.conductor', $guia->conductor?->nombre_completo ?? $guia->conductor_nombre)
            ->where('pendientes.0.tickets.0.codigo', $vuelta->codigo)
            // El vacío no se ofrece: no lleva GR.
            ->has('pendientes.0.tickets.0.paradas', 2)
            ->where('pendientes.0.sugerencia.parada_id', paradaDe($vuelta, 1)->id)
            ->where('sinTicket.0.motivo', 'En ruta')
            ->where('puedeAsignar', true));
});

it('shows the driver of the padrón, or the name on the GR when it is not linked', function (): void {
    $conductor = Conductor::factory()->create(['nombres' => 'Juan Carlos', 'apellidos' => 'Quispe Mamani']);
    $enlazada = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    $enlazada->update(['conductor_id' => $conductor->id, 'conductor_nombre' => 'QUISPE MAMANI JUAN']);
    $suelta = registrarGuia($this->tracto, '2026-11-06', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    $suelta->update(['conductor_id' => null, 'conductor_nombre' => 'PEREZ LOPEZ PEDRO']);

    actingAs($this->admin)
        ->get(route('vueltas.guias'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendientes.0.conductor', 'Juan Carlos Quispe Mamani')
            ->where('pendientes.1.conductor', 'PEREZ LOPEZ PEDRO'));
});

it('only lets whoever has the permission assign GR', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-05', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    actingAs(actorConRol('visor'))
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => paradaDe($vuelta, 1)->id])
        ->assertForbidden();
});

/**
 * Caso V-00007: bajó con gaseosa a Arequipa, se programó vacía a Juliaca y
 * consiguió arroz en Uraca.
 */
function vueltaConRegresoVacio(Vehiculo $tracto): Vuelta
{
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $tracto->id, 'inicio' => '2026-11-06']);

    foreach ([
        [ZonaRuta::Lima, ZonaRuta::Arequipa, TipoCarga::Particular, 'Huaral', 'Arequipa'],
        [ZonaRuta::Arequipa, ZonaRuta::Juliaca, TipoCarga::Vacio, 'Arequipa', 'Juliaca'],
    ] as $orden => [$origen, $destino, $carga, $lugarOrigen, $lugarDestino]) {
        ParadaVuelta::factory()->create([
            'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => $orden + 1,
            'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga,
            'lugar_origen' => $lugarOrigen, 'lugar_destino' => $lugarDestino, 'cliente' => null, 'cliente_id' => null, 'fecha' => null,
        ]);
    }

    return $vuelta;
}

const DIR_URACA = 'FUNDO SAN VICENTE KM 3 SN - URACA - CASTILLA - AREQUIPA';

it('suggests the empty run of the same route and the GR takes its place', function (): void {
    $vuelta = vueltaConRegresoVacio($this->tracto);
    $vacio = paradaDe($vuelta, 2);
    $guia = registrarGuia($this->tracto, '2026-11-10', DIR_URACA, DIR_JULIACA, TipoCarga::Particular);

    actingAs($this->admin)
        ->get(route('vueltas.guias'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendientes.0.sugerencia.parada_id', $vacio->id)
            ->has('pendientes.0.tickets.0.paradas', 2)
            ->where('pendientes.0.tickets.0.paradas.1.vacio', true)
            ->has('tramos.regreso'));

    actingAs($this->admin)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => $vacio->id])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('carga_sin_programar.vuelta_id', $vuelta->id)
        ->assertInertiaFlash('carga_sin_programar.sentido', 'regreso')
        ->assertInertiaFlash('carga_sin_programar.trayecto', 'Uraca → Juliaca');

    $vacio->refresh();
    expect($guia->fresh()->parada_vuelta_id)->toBe($vacio->id)
        ->and($vacio->tipo_carga)->toBe(TipoCarga::Particular)
        // El lugar real de la GR manda sobre el que puso la ruta.
        ->and($vacio->lugar_origen)->toBe('Uraca')
        ->and($vacio->cliente)->toBe($guia->cliente)
        ->and($vacio->fuera_de_programa)->toBeFalse()
        ->and($vuelta->paradas()->count())->toBe(2);
});

it('keeps the place of the first GR when a second one arrives at the same stop', function (): void {
    $vuelta = vueltaConRegresoVacio($this->tracto);
    $bajada = paradaDe($vuelta, 1);
    $primera = registrarGuia($this->tracto, '2026-11-07', DIR_HUARAL, 'MZ. 5 LT. 1 - CERRO COLORADO - AREQUIPA - AREQUIPA', TipoCarga::Particular);
    $segunda = registrarGuia($this->tracto, '2026-11-07', DIR_HUARAL, 'ZONA 19 - YURA - AREQUIPA - AREQUIPA', TipoCarga::Particular);

    foreach ([$primera, $segunda] as $guia) {
        actingAs($this->admin)
            ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'parada_vuelta_id' => $bajada->id])
            ->assertSessionHasNoErrors()
            // Estaba programada: no se ofrece sumar combustible.
            ->assertInertiaFlashMissing('carga_sin_programar');
    }

    expect($bajada->fresh()->lugar_destino)->toBe('Cerro Colorado');
});

it('offers the fuel of an unplanned load only to whoever can edit the ticket', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-11-05');
    $guia = registrarGuia($this->tracto, '2026-11-07', DIR_PISCO, DIR_CALLAO, TipoCarga::Metalico);
    $soloAsigna = actorConRol('visor');
    $soloAsigna->givePermissionTo('programacion.asignar_gr');

    actingAs($soloAsigna)
        ->post(route('viajes.ticket.asignar', $guia), ['vuelta_id' => $vuelta->id, 'sentido' => 'ida'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlashMissing('carga_sin_programar');
});
