<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\TipoVuelta;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\ReconstructorVueltas;

const MINA = 'ASIENTO MINERO SAN RAFAEL KM.102.6 Puno - ANTAUTA - MELGAR - PUNO';
const PISCO = 'CAR. PANAMERICANA SUR KM 245 - PARACAS - PISCO - ICA';
const CALLAO = 'AV. ELMER FAUCETT 3348 - CALLAO - PROV. CONST. DEL CALLAO - PROV. CONST. DEL CALLAO';
const HUARAL = 'PARCELA NRO 55 - HUARAL - HUARAL - LIMA';
const JULIACA = 'JR. 8 NOVIEMBRE 615 - JULIACA - SAN ROMAN - PUNO';

function guia(Vehiculo $tracto, string $fecha, string $origen, string $destino, TipoCarga $carga): Viaje
{
    return Viaje::factory()->create([
        'tracto_id' => $tracto->id,
        'placa_tracto' => $tracto->placa,
        'fecha_traslado' => $fecha,
        'fecha_emision' => $fecha,
        'origen' => $origen,
        'destino' => $destino,
        'tipo_carga' => $carga,
    ]);
}

/**
 * @return list<string> cada parada como «sentido:carga»
 */
function recorrido(Vuelta $vuelta): array
{
    return $vuelta->paradas()->get()
        ->map(fn (ParadaVuelta $parada): string => "{$parada->sentido->value}:{$parada->tipo_carga->value}")
        ->all();
}

beforeEach(function (): void {
    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

it('splits the history into outbound and return legs, adding the empty runs', function (): void {
    $materiales = guia($this->tracto, '2026-08-28', CALLAO, MINA, TipoCarga::Materiales);
    $concentrado = guia($this->tracto, '2026-09-02', MINA, PISCO, TipoCarga::Concentrado);
    guia($this->tracto, '2026-09-07', PISCO, CALLAO, TipoCarga::Metalico);
    guia($this->tracto, '2026-09-09', HUARAL, JULIACA, TipoCarga::Particular);
    guia($this->tracto, '2026-09-16', MINA, PISCO, TipoCarga::Concentrado);

    $resumen = app(ReconstructorVueltas::class)->reconstruir();

    expect($resumen)->toBe(['vueltas' => 3, 'paradas' => 6, 'vacios' => 1, 'guias' => 5]);

    [$anterior, $vuelta, $siguiente] = Vuelta::query()->orderBy('inicio')->get()->all();

    // Los materiales subieron a mina: es el regreso de una vuelta que bajó
    // antes de que hubiera GR.
    expect(recorrido($anterior))->toBe(['regreso:materiales'])
        ->and($anterior->estado)->toBe(EstadoVuelta::Cerrada)
        ->and($materiales->fresh()->parada->vuelta->is($anterior))->toBeTrue();

    // La vuelta sale de la mina: baja concentrado y metálico y sube con
    // particular a Juliaca. Ahí termina.
    expect(recorrido($vuelta))->toBe(['ida:concentrado', 'ida:metalico', 'regreso:particular'])
        ->and($vuelta->estado)->toBe(EstadoVuelta::Cerrada)
        ->and($vuelta->tipo)->toBe(TipoVuelta::Mina)
        ->and($vuelta->inicio->toDateString())->toBe('2026-09-02')
        ->and($vuelta->fin->toDateString())->toBe('2026-09-09')
        ->and($vuelta->codigo)->toStartWith('V-')
        ->and($concentrado->fresh()->parada->vuelta->is($vuelta))->toBeTrue();

    // De Juliaca sube vacía a la mina: ahí se abastece y arranca la vuelta
    // siguiente.
    expect(recorrido($siguiente))->toBe(['ida:vacio', 'ida:concentrado'])
        ->and($siguiente->estado)->toBe(EstadoVuelta::EnCurso)
        ->and($siguiente->fin)->toBeNull();

    $vacio = $siguiente->paradas()->where('tipo_carga', TipoCarga::Vacio)->sole();
    expect($vacio->lugar_origen)->toBe('Juliaca')
        ->and($vacio->lugar_destino)->toBe('Antauta')
        ->and($vacio->viajes)->toBeEmpty();
});

it('groups the GR of the same load into one stop', function (): void {
    guia($this->tracto, '2026-09-21', MINA, CALLAO, TipoCarga::Materiales);
    guia($this->tracto, '2026-09-21', MINA, CALLAO, TipoCarga::Materiales);
    guia($this->tracto, '2026-09-22', MINA, CALLAO, TipoCarga::Materiales);

    app(ReconstructorVueltas::class)->reconstruir();

    $parada = ParadaVuelta::query()->sole();
    expect($parada->viajes)->toHaveCount(3)
        ->and($parada->sentido)->toBe(SentidoTramo::Ida);
});

it('marks a round trip with only private cargo as particular', function (): void {
    guia($this->tracto, '2026-09-04', JULIACA, CALLAO, TipoCarga::Particular);
    guia($this->tracto, '2026-09-09', HUARAL, JULIACA, TipoCarga::Particular);

    app(ReconstructorVueltas::class)->reconstruir();

    $vuelta = Vuelta::query()->sole();
    expect($vuelta->tipo)->toBe(TipoVuelta::Particular)
        ->and(recorrido($vuelta))->toBe(['ida:particular', 'regreso:particular']);
});

it('gives the same result when run again', function (): void {
    guia($this->tracto, '2026-09-02', MINA, PISCO, TipoCarga::Concentrado);
    guia($this->tracto, '2026-09-07', PISCO, MINA, TipoCarga::Escoria);
    guia($this->tracto, '2026-09-12', MINA, PISCO, TipoCarga::Concentrado);

    $antes = app(ReconstructorVueltas::class)->reconstruir();
    $despues = app(ReconstructorVueltas::class)->reconstruir();

    expect($despues)->toBe($antes)
        ->and(Vuelta::query()->count())->toBe($antes['vueltas'])
        ->and(Viaje::query()->whereNull('parada_vuelta_id')->count())->toBe(0);
});

it('never touches programmed trips nor their GR', function (): void {
    $programada = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id]);
    $parada = ParadaVuelta::factory()->create(['vuelta_id' => $programada->id]);
    $suya = guia($this->tracto, '2026-09-02', MINA, PISCO, TipoCarga::Concentrado);
    $suya->forceFill(['parada_vuelta_id' => $parada->id])->save();
    guia($this->tracto, '2026-09-10', HUARAL, JULIACA, TipoCarga::Particular);

    app(ReconstructorVueltas::class)->reconstruir();

    expect($programada->fresh())->not->toBeNull()
        ->and($suya->fresh()->parada_vuelta_id)->toBe($parada->id)
        ->and(Vuelta::query()->where('origen', OrigenVuelta::Historial)->count())->toBe(1);
});

it('does not save anything on a dry run', function (): void {
    // Una GR de antes de que existieran las vueltas: hoy se asignaría sola
    // al registrarse, así que se la suelta para simularlo.
    guia($this->tracto, '2026-09-02', MINA, PISCO, TipoCarga::Concentrado);
    Viaje::query()->update(['parada_vuelta_id' => null]);
    Vuelta::query()->delete();

    $this->artisan('transpaty:reconstruir-vueltas', ['--dry-run' => true])
        ->expectsOutputToContain('[dry-run] Vueltas: 1')
        ->assertSuccessful();

    expect(Vuelta::query()->count())->toBe(0);

    $this->artisan('transpaty:reconstruir-vueltas')->assertSuccessful();

    expect(Vuelta::query()->count())->toBe(1);
});
