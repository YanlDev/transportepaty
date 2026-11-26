<?php

use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\ProgramadorVueltas;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/**
 * Poner al día los tickets de los viajes ya hechos: se arman desde las GR de
 * la unidad, con las rutas de las que sale el tope de combustible, las GR
 * asignadas y el OK de taller. No se registra combustible ni se cierra nada.
 */
const MINA = 'CAR. DESVIO C.JULIACA-MACUSANI KM. 102 - ANTAUTA - MELGAR - PUNO';
const PLANTA_PISCO = 'CAR. CARRET.PANAMERICANA SUR KM. 240 - PARACAS - PISCO - ICA';
const CALLAO = 'CALLE JUAN MILLER NRO. 176 - CALLAO - CALLAO - CALLAO';
const HUARAL = 'PARCELA NRO 55 ZONA CAMPO GRAN - HUARAL - HUARAL - LIMA';
const JULIACA = 'URB. ZONA INDUSTRIAL TAPARACHI MZ. A - JULIACA - SAN ROMAN - PUNO';

beforeEach(function (): void {
    User::factory()->create(['username' => 'admin']);
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

function guiaDe(Vehiculo $tracto, string $fecha, TipoCarga $carga, string $origen, string $destino): Viaje
{
    return Viaje::factory()->tipoCarga($carga)->create([
        'tracto_id' => $tracto->id,
        'placa_tracto' => $tracto->placa,
        'fecha_emision' => $fecha,
        'fecha_traslado' => $fecha,
        'origen' => $origen,
        'destino' => $destino,
    ]);
}

it('builds the ticket of a finished trip from its waybills, with the fuel cap and the workshop OK', function (): void {
    $concentrado = guiaDe($this->tracto, '2026-11-07', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    $metalico = guiaDe($this->tracto, '2026-11-12', TipoCarga::Metalico, PLANTA_PISCO, CALLAO);
    $crisar = guiaDe($this->tracto, '2026-11-14', TipoCarga::Particular, HUARAL, JULIACA);

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();

    $ticket = Vuelta::query()->sole();

    expect($ticket->estado)->toBe(EstadoVuelta::EnCurso)
        ->and($ticket->inicio->toDateString())->toBe('2026-11-07')
        ->and($ticket->rutas->pluck('nombre')->all())->toBe(['Concentrado a Pisco', 'Metálico al Callao', 'Particular a Juliaca', 'Ida y vuelta Huaral'])
        // 220 del concentrado + 10 del metálico; 190 hasta Juliaca + 10 de
        // Huaral (serían 20, pero el metálico ya dio 10 hasta el Callao).
        ->and($ticket->topeDe(SentidoTramo::Ida))->toBe(230.0)
        ->and($ticket->topeDe(SentidoTramo::Regreso))->toBe(200.0)
        ->and($ticket->tieneOkDeTaller())->toBeTrue()
        ->and($ticket->abastecimientos()->count())->toBe(0)
        ->and($ticket->viajes()->pluck('viajes.id')->sort()->values()->all())->toBe([$concentrado->id, $metalico->id, $crisar->id])
        ->and($crisar->fresh()->parada->sentido)->toBe(SentidoTramo::Regreso)
        ->and($crisar->fresh()->parada->fuera_de_programa)->toBeFalse();
});

it('only simulates unless told to apply', function (): void {
    guiaDe($this->tracto, '2026-11-07', TipoCarga::Concentrado, MINA, PLANTA_PISCO);

    artisan('transpaty:regularizar-tickets')->assertSuccessful();

    expect(Vuelta::query()->count())->toBe(0)
        ->and(Viaje::query()->whereNotNull('parada_vuelta_id')->count())->toBe(0);
});

it('completes the ticket that was already programmed instead of making another', function (): void {
    $programado = app(ProgramadorVueltas::class)->programar([
        'tracto_id' => $this->tracto->id,
        'carreta_id' => null,
        'conductor_id' => null,
        'inicio' => '2026-11-06',
        'paradas' => [
            ['sentido' => 'ida', 'zona_origen' => 'juliaca', 'zona_destino' => 'mina', 'tipo_carga' => 'vacio'],
            ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
        ],
        'rutas' => [['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null]],
    ]);
    guiaDe($this->tracto, '2026-11-07', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    guiaDe($this->tracto, '2026-11-12', TipoCarga::Metalico, PLANTA_PISCO, CALLAO);

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();

    $ticket = Vuelta::query()->sole();

    expect($ticket->id)->toBe($programado->id)
        ->and($ticket->viajes()->count())->toBe(2)
        ->and($ticket->topeDe(SentidoTramo::Ida))->toBe(230.0);
});

it('starts a new ticket when the unit goes down again, and notes the arrival of the one before', function (): void {
    guiaDe($this->tracto, '2026-11-05', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    guiaDe($this->tracto, '2026-11-09', TipoCarga::Escoria, PLANTA_PISCO, MINA);
    guiaDe($this->tracto, '2026-11-14', TipoCarga::Concentrado, MINA, PLANTA_PISCO);

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();

    [$primero, $segundo] = Vuelta::query()->with('rutas')->orderBy('id')->get()->all();

    expect(Vuelta::query()->count())->toBe(2)
        ->and($primero->topeDe(SentidoTramo::Regreso))->toBe(130.0)
        // El día antes de volver a salir.
        ->and($primero->llegada_el->toDateString())->toBe('2026-11-13')
        ->and($primero->llegada_deducida)->toBeTrue()
        // Sube con la escoria que trajo: 10 galones más.
        ->and($segundo->topeDe(SentidoTramo::Ida))->toBe(230.0)
        ->and($segundo->llegada_el)->toBeNull();
});

it('leaves out the way down when the unit was already on its way back at the cut', function (): void {
    $concentrado = guiaDe($this->tracto, '2026-10-30', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    $metalico = guiaDe($this->tracto, '2026-11-04', TipoCarga::Metalico, PLANTA_PISCO, CALLAO);
    $crisar = guiaDe($this->tracto, '2026-11-06', TipoCarga::Particular, HUARAL, JULIACA);

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();

    $ticket = Vuelta::query()->sole();

    expect($ticket->rutas->pluck('nombre')->all())->toBe(['Particular a Juliaca', 'Ida y vuelta Huaral'])
        ->and($ticket->topeDe(SentidoTramo::Ida))->toBeNull()
        ->and($ticket->inicio->toDateString())->toBe('2026-11-06')
        ->and($ticket->viajes()->pluck('viajes.id')->all())->toBe([$crisar->id])
        ->and($concentrado->fresh()->parada_vuelta_id)->toBeNull()
        ->and($metalico->fresh()->parada_vuelta_id)->toBeNull()
        ->and($ticket->por_revisar)->toBeNull();
});

it('puts a waybill without a readable route on the stop of the route it is told, and flags what needs a look', function (): void {
    guiaDe($this->tracto, '2026-11-07', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    guiaDe($this->tracto, '2026-11-13', TipoCarga::Particular, HUARAL, JULIACA);
    $transbordo = guiaDe($this->tracto, '2026-11-15', TipoCarga::Particular, 'CARRETERA PANAMERICANA NORTE KM. 694 - GUADALUPE - PACASMAYO - LA LIBERTAD', '');
    $sinTrayecto = guiaDe($this->tracto, '2026-11-15', TipoCarga::Particular, 'AV. UNO - LURIN - LIMA - LIMA', 'AV. DOS - VEGUETA - HUAURA - LIMA');

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true, '--trayecto' => ["{$transbordo->numero_gr}:lima:juliaca"]])->assertSuccessful();

    $ticket = Vuelta::query()->sole();
    $parada = $transbordo->fresh()->parada;

    expect($parada->vuelta_id)->toBe($ticket->id)
        ->and($parada->zona_destino)->toBe(ZonaRuta::Juliaca)
        ->and($parada->sentido)->toBe(SentidoTramo::Regreso)
        ->and($sinTrayecto->fresh()->parada_vuelta_id)->toBeNull()
        ->and($ticket->por_revisar)->toContain($sinTrayecto->numero_gr);
});

it('lets whoever can edit the ticket clear the review flag', function (): void {
    guiaDe($this->tracto, '2026-11-07', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();
    $ticket = Vuelta::query()->sole();
    $ticket->update(['por_revisar' => 'Sin GR de bajada']);

    actingAs(actorConRol('visor'))->post(route('vueltas.revisado', $ticket))->assertForbidden();
    actingAs(actorConRol('admin'))->post(route('vueltas.revisado', $ticket))->assertRedirect();

    expect($ticket->fresh()->por_revisar)->toBeNull();
});

/*
 * Los 180 gal de Chilca son para quien baja de Pisco a Chilca sin llegar a
 * Lima. Quien llegó cargado a Lima y de ahí bajó a Chilca hace el recorrido
 * entero: «Particular a Juliaca», 190.
 */
it('gives the full Lima route to a unit that reached Lima loaded before loading in Chilca', function (): void {
    guiaDe($this->tracto, '2026-11-19', TipoCarga::Particular, 'CAR. JULIACA-PUNO KM. 11 HACIENDA YUNGURA - CARACOTO - SAN ROMAN - PUNO', 'INT. D SEC. PAMPA DE CAJAMARQUILLA (SUB LOTE D) - LURIGANCHO - LIMA - LIMA');
    guiaDe($this->tracto, '2026-11-23', TipoCarga::Particular, 'OTR. SUB LOTE C-D-E-F AREA INDUSTRIAL - CHILCA - CAÑETE - LIMA', JULIACA);

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();

    $ticket = Vuelta::query()->sole();

    expect($ticket->rutas->pluck('nombre')->all())->toBe(['Particular a Lima', 'Particular a Juliaca'])
        ->and($ticket->topeDe(SentidoTramo::Regreso))->toBe(190.0);
});

it('keeps the Chilca route for a unit that comes down from Pisco without reaching Lima loaded', function (): void {
    guiaDe($this->tracto, '2026-11-07', TipoCarga::Concentrado, MINA, PLANTA_PISCO);
    guiaDe($this->tracto, '2026-11-12', TipoCarga::Particular, 'OTR. SUB LOTE C-D-E-F AREA INDUSTRIAL - CHILCA - CAÑETE - LIMA', JULIACA);

    artisan('transpaty:regularizar-tickets', ['--aplicar' => true])->assertSuccessful();

    $ticket = Vuelta::query()->sole();

    expect($ticket->rutas->pluck('nombre')->all())->toContain('Particular de Chilca a Juliaca')
        ->and($ticket->topeDe(SentidoTramo::Regreso))->toBe(180.0);
});
