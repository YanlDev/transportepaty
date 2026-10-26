<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AsignadorVueltas;

const DIR_MINA = 'ASIENTO MINERO SAN RAFAEL Puno - ANTAUTA - MELGAR - PUNO';
const DIR_PISCO = 'CAR. PANAMERICANA SUR KM 245 - PARACAS - PISCO - ICA';
const DIR_CALLAO = 'AV. ELMER FAUCETT 3348 - CALLAO - PROV. CONST. DEL CALLAO - PROV. CONST. DEL CALLAO';
const DIR_HUARAL = 'PARCELA NRO 55 - HUARAL - HUARAL - LIMA';
const DIR_JULIACA = 'JR. 8 NOVIEMBRE 615 - JULIACA - SAN ROMAN - PUNO';

/**
 * Registrar una GR es lo que dispara la asignación (evento `created`).
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
 * La vuelta de la plantilla «concentrado · particular a Juliaca»: baja con
 * concentrado y vacía a Lima, sube con particular a Juliaca y vacía a mina.
 */
function programarVuelta(Vehiculo $tracto, string $salida): Vuelta
{
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $tracto->id, 'inicio' => $salida]);

    foreach ([
        [SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado],
        [SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Vacio],
        [SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Juliaca, TipoCarga::Particular],
        [SentidoTramo::Regreso, ZonaRuta::Juliaca, ZonaRuta::Mina, TipoCarga::Vacio],
    ] as $orden => [$sentido, $origen, $destino, $carga]) {
        ParadaVuelta::factory()->create([
            'vuelta_id' => $vuelta->id, 'sentido' => $sentido, 'orden' => $orden + 1,
            'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga,
            'lugar_origen' => null, 'lugar_destino' => null, 'cliente' => null, 'fecha' => null,
        ]);
    }

    return $vuelta;
}

beforeEach(function (): void {
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

it('drops a new GR into the programmed stop it matches', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-10-10');

    $guia = registrarGuia($this->tracto, '2026-10-10', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    $parada = $guia->parada;
    expect($parada->vuelta->is($vuelta))->toBeTrue()
        ->and($parada->orden)->toBe(1)
        ->and($parada->fuera_de_programa)->toBeFalse()
        ->and($parada->lugar_origen)->toBe('Antauta')
        ->and($parada->fecha->toDateString())->toBe('2026-10-10')
        ->and($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);

    // El particular de la subida cae en su parada, saltando el vacío.
    $particular = registrarGuia($this->tracto, '2026-10-15', DIR_HUARAL, DIR_JULIACA, TipoCarga::Particular);
    expect($particular->parada->orden)->toBe(3);
});

it('accepts the GR one day before the planned departure', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-10-10');

    $guia = registrarGuia($this->tracto, '2026-10-09', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    expect($guia->parada->vuelta->is($vuelta))->toBeTrue();
});

it('adds what was not programmed as an unplanned stop', function (): void {
    $vuelta = programarVuelta($this->tracto, '2026-10-10');

    $guia = registrarGuia($this->tracto, '2026-10-11', DIR_PISCO, DIR_CALLAO, TipoCarga::Metalico);

    expect($guia->parada->vuelta->is($vuelta))->toBeTrue()
        ->and($guia->parada->fuera_de_programa)->toBeTrue()
        ->and($guia->parada->sentido)->toBe(SentidoTramo::Ida)
        ->and($guia->parada->orden)->toBe(5);
});

it('joins several GR of the same load in one stop', function (): void {
    programarVuelta($this->tracto, '2026-10-10');

    $primera = registrarGuia($this->tracto, '2026-10-10', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    $segunda = registrarGuia($this->tracto, '2026-10-10', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    expect($segunda->parada_vuelta_id)->toBe($primera->parada_vuelta_id);
});

it('opens the next trip when the unit goes down again after coming back up', function (): void {
    $programada = programarVuelta($this->tracto, '2026-10-10');
    registrarGuia($this->tracto, '2026-10-10', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    registrarGuia($this->tracto, '2026-10-15', DIR_HUARAL, DIR_JULIACA, TipoCarga::Particular);

    $siguiente = registrarGuia($this->tracto, '2026-10-24', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    expect($siguiente->parada->vuelta->is($programada))->toBeFalse()
        ->and($siguiente->parada->vuelta->estado)->toBe(EstadoVuelta::EnCurso)
        ->and($programada->fresh()->estado)->toBe(EstadoVuelta::Cerrada)
        ->and($programada->fresh()->fin->toDateString())->toBe('2026-10-15');
});

it('keeps building the history when nothing is programmed', function (): void {
    registrarGuia($this->tracto, '2026-09-02', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);
    registrarGuia($this->tracto, '2026-09-07', DIR_PISCO, DIR_CALLAO, TipoCarga::Metalico);
    registrarGuia($this->tracto, '2026-09-09', DIR_HUARAL, DIR_JULIACA, TipoCarga::Particular);
    // Vuelve a bajar después de subir: se cierra la vuelta y empieza otra.
    $bajada = registrarGuia($this->tracto, '2026-09-16', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    [$primera, $segunda] = Vuelta::query()->orderBy('id')->get()->all();

    expect($primera->origen)->toBe(OrigenVuelta::Historial)
        ->and($primera->estado)->toBe(EstadoVuelta::Cerrada)
        ->and($primera->fin->toDateString())->toBe('2026-09-09')
        // Callao y Huaral son la misma zona: entre las dos no hay vacío; de
        // Juliaca a la mina sí, y es parte del regreso.
        ->and($primera->paradas()->count())->toBe(4)
        ->and($segunda->estado)->toBe(EstadoVuelta::EnCurso)
        ->and($bajada->parada->vuelta->is($segunda))->toBeTrue()
        ->and($segunda->paradas()->count())->toBe(1);
});

it('leaves the GR before the planned departure in the history', function (): void {
    $programada = programarVuelta($this->tracto, '2026-10-20');

    $guia = registrarGuia($this->tracto, '2026-10-01', DIR_MINA, DIR_PISCO, TipoCarga::Concentrado);

    expect($guia->parada->vuelta->is($programada))->toBeFalse()
        ->and($guia->parada->vuelta->origen)->toBe(OrigenVuelta::Historial);
});

it('ignores a GR without a tractor', function (): void {
    $guia = Viaje::factory()->create(['tracto_id' => null]);

    expect($guia->fresh()->parada_vuelta_id)->toBeNull()
        ->and(Vuelta::query()->count())->toBe(0);
});

it('registers the GR even if building its trip fails', function (): void {
    $this->mock(AsignadorVueltas::class)
        ->shouldReceive('asignar')
        ->andThrow(new RuntimeException('falla'));

    $guia = registrarGuia($this->tracto, '2026-10-10', DIR_HUARAL, DIR_JULIACA, TipoCarga::Particular);

    expect($guia->exists)->toBeTrue()
        ->and($guia->parada_vuelta_id)->toBeNull();
});
