<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\LecturaKilometraje;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Viatico;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * La GR de regreso se emite al cargar (la de Ransa, en el Callao): la unidad
 * sigue en vuelta hasta que alguien registra que llegó a la base, y el
 * ticket se cierra solo con la liquidación completa.
 */
beforeEach(function (): void {
    foreach (['admin', 'operaciones'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

/**
 * Un ticket apto de taller (salió con 250 000 km) cuya GR de Ransa (Lima →
 * mina) salió ese día, con la tarjeta del regreso confirmada.
 */
function ticketConRansa(Vehiculo $tracto, string $fechaGr): Vuelta
{
    $vuelta = Vuelta::factory()->create([
        'tracto_id' => $tracto->id, 'origen' => OrigenVuelta::Programacion, 'inicio' => now()->subDays(10)->toDateString(),
    ]);
    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id, 'kilometraje' => 250000]);
    $parada = ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1,
        'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Materiales,
    ]);
    Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => $fechaGr])
        ->forceFill(['parada_vuelta_id' => $parada->id])->save();
    RutaVuelta::factory()->for($vuelta)->regreso()->create();

    return $vuelta->load('paradas.viajes');
}

function llegar(Vuelta $vuelta, string $fecha, ?int $km = 251800): void
{
    actingAs(actorConRol('admin'))
        ->post(route('vueltas.llegada', $vuelta), ['llegada_el' => $fecha, 'km_llegada' => $km])
        ->assertSessionHasNoErrors();
}

it('keeps the unit on its way back until someone registers that it arrived', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(10)->toDateString());

    // Días después de la GR sigue en vuelta: la llegada no se calcula.
    expect($vuelta->etapa())->toBe('en_vuelta');

    llegar($vuelta, now()->toDateString());

    $vuelta->refresh();
    expect($vuelta->etapa())->toBe('en_base')
        ->and($vuelta->km_llegada)->toBe(251800)
        ->and(LecturaKilometraje::query()->where('vehiculo_id', $this->tracto->id)->value('kilometraje'))->toBe(251800);
});

it('flags the arrival as late once the expected day went by', function (): void {
    ticketConRansa($this->tracto, now()->subDays(10)->toDateString());

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('tablero.0.atrasada', true)->where('tablero.0.etapa', 'en_vuelta'));
});

it('checks the day and the odometer of the arrival', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(2)->toDateString());
    LecturaKilometraje::factory()->create(['vehiculo_id' => $this->tracto->id, 'kilometraje' => 260000]);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.llegada', $vuelta), ['llegada_el' => now()->subDays(3)->toDateString(), 'km_llegada' => 261000])
        ->assertSessionHasErrors('llegada_el');
    actingAs(actorConRol('admin'))
        ->post(route('vueltas.llegada', $vuelta), ['llegada_el' => now()->toDateString(), 'km_llegada' => 255000])
        ->assertSessionHasErrors('km_llegada');
    actingAs(actorConRol('abastecimiento'))
        ->post(route('vueltas.llegada', $vuelta), ['llegada_el' => now()->toDateString()])
        ->assertForbidden();
});

it('does not program the next ticket while the unit is still on the road', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(2)->toDateString());
    $datos = [
        'tracto_id' => $this->tracto->id,
        'inicio' => now()->toDateString(),
        'paradas' => [['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']],
        'rutas' => [['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null]],
    ];

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), $datos)->assertSessionHasErrors('tracto_id');

    llegar($vuelta, now()->toDateString());

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), $datos)->assertSessionHasNoErrors();

    // Programar el siguiente no cierra el anterior: eso es de la liquidación.
    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);
});

it('still lets fuel be registered at Kio after the Ransa GR', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page->where('vueltas.0.id', $vuelta->id)->where('vueltas.0.etapa', 'en_vuelta'));

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), ['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'sentido' => 'regreso', 'fecha' => now()->toDateString(), 'galones' => 80])
        ->assertSessionHasNoErrors();
});

it('keeps a ticket in base visible until it is closed', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(20)->toDateString());
    llegar($vuelta, now()->subDays(15)->toDateString());

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page->where('vueltas.0.id', $vuelta->id)->where('vueltas.0.etapa', 'en_base'));

    $vuelta->update(['estado' => EstadoVuelta::Cerrada]);

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page->has('vueltas', 0));
});

it('closes only with the settlement complete: arrival and every voucher', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(2)->toDateString());
    $combustible = Abastecimiento::factory()->paraVuelta($vuelta, SentidoTramo::Regreso)->create(['galones' => 130]);

    // Sin llegada, ni se ofrece.
    actingAs(actorConRol('admin'))->post(route('vueltas.cerrar', $vuelta))->assertForbidden();

    llegar($vuelta, now()->toDateString());

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.cerrar', $vuelta))
        ->assertSessionHas('toast.type', 'error');
    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);

    actingAs(actorConRol('admin'))->put(route('abastecimiento.despacho', $combustible), [
        'galones_despachados' => 120, 'precio_galon' => 16, 'despachado_el' => now()->toDateString(),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $vuelta))
        ->assertInertia(fn (Assert $page) => $page
            ->where('liquidacion.puede_cerrar', true)
            ->where('liquidacion.km.recorridos', 1800)
            ->where('liquidacion.rendimiento', 15)
            ->where('liquidacion.combustible.diferencia', -10)
            ->where('liquidacion.combustible.importe', 1920));

    actingAs(actorConRol('admin'))->post(route('vueltas.cerrar', $vuelta))->assertSessionHas('toast.type', 'success');

    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::Cerrada)
        ->and($vuelta->fresh()->fin->toDateString())->toBe(now()->toDateString());
});

it('locks a closed ticket: no more fuel nor allowances until it is reopened', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());
    $vuelta->update(['estado' => EstadoVuelta::Cerrada]);
    $datos = ['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'sentido' => 'regreso', 'fecha' => now()->toDateString(), 'galones' => 80];

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), $datos)
        ->assertSessionHasErrors('vuelta_id');

    actingAs(actorConRol('abastecimiento'))
        ->post(route('viaticos.store'), ['vuelta_id' => $vuelta->id, 'concepto' => 'Imprevisto', 'monto' => 50, 'fecha' => now()->toDateString()])
        ->assertSessionHasErrors('vuelta_id');

    actingAs(actorConRol('admin'))->post(route('vueltas.reabrir', $vuelta))->assertSessionHas('toast.type', 'success');

    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), $datos)
        ->assertSessionHasNoErrors();
});

it('does not touch what a closed or voided ticket already has', function (EstadoVuelta $estado): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());
    $abastecimiento = Abastecimiento::factory()->paraVuelta($vuelta)->create();
    $viatico = Viatico::factory()->create(['vuelta_id' => $vuelta->id, 'vehiculo_id' => $this->tracto->id]);
    $vuelta->update(['estado' => $estado]);

    actingAs(actorConRol('abastecimiento'))->post(route('abastecimiento.anular', $abastecimiento), ['motivo' => 'error'])->assertForbidden();
    actingAs(actorConRol('abastecimiento'))->put(route('abastecimiento.despacho', $abastecimiento), [
        'galones_despachados' => 50, 'precio_galon' => 16, 'despachado_el' => now()->toDateString(),
    ])->assertForbidden();
    actingAs(actorConRol('abastecimiento'))->post(route('viaticos.anular', $viatico), ['motivo' => 'error'])->assertForbidden();
})->with([
    'cerrado' => EstadoVuelta::Cerrada,
    'anulado' => EstadoVuelta::Anulada,
]);

it('corrects a mistyped arrival odometer instead of keeping the wrong reading', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->toDateString());

    llegar($vuelta, now()->toDateString(), 2518000);
    llegar($vuelta->fresh(), now()->toDateString(), 251800);

    expect(LecturaKilometraje::query()->where('vehiculo_id', $this->tracto->id)->pluck('kilometraje')->all())->toBe([251800]);
});

it('only lets fuel and accounting settle, and reopens only the latest ticket', function (): void {
    $vuelta = ticketConRansa($this->tracto, now()->subDays(5)->toDateString());
    llegar($vuelta, now()->toDateString());

    actingAs(actorConRol('operaciones'))->post(route('vueltas.cerrar', $vuelta))->assertForbidden();
    actingAs(actorConRol('taller'))->post(route('vueltas.cerrar', $vuelta))->assertForbidden();
    actingAs(actorConRol('contador'))->post(route('vueltas.cerrar', $vuelta))->assertSessionHas('toast.type', 'success');
    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::Cerrada);

    $vuelta->update(['estado' => EstadoVuelta::Cerrada]);
    Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => now()->toDateString()]);

    actingAs(actorConRol('admin'))->post(route('vueltas.reabrir', $vuelta))->assertForbidden();
});
