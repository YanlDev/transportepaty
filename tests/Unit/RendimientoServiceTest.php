<?php

use App\Models\CargaCombustible;
use App\Services\Combustible\RendimientoService;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Booteamos la app (sin RefreshDatabase) para que el cast de fecha resuelva su
// formato; el cálculo es puro y no consulta la base de datos.
uses(TestCase::class);

/**
 * Builds an in-memory load (no DB) for the pure calculation.
 */
function carga(int $id, string $fecha, ?int $odometro, ?float $galones, ?float $costo = null): CargaCombustible
{
    return (new CargaCombustible)->forceFill([
        'id' => $id,
        'fecha_carga' => $fecha,
        'odometro' => $odometro,
        'galones' => $galones,
        'costo_total' => $costo,
    ]);
}

/**
 * @param  array<int, CargaCombustible>  $cargas
 */
function calcular(array $cargas): array
{
    return (new RendimientoService)->calcular(new Collection($cargas));
}

it('returns no efficiency for the first load (baseline)', function (): void {
    $resultado = calcular([
        carga(1, '2026-01-01', 1000, 50),
    ]);

    expect($resultado['porCarga'][0]['km_recorridos'])->toBeNull()
        ->and($resultado['porCarga'][0]['rendimiento'])->toBeNull()
        ->and($resultado['porCarga'][0]['anomalia'])->toBeFalse()
        ->and($resultado['resumen']['rendimiento_promedio'])->toBeNull();
});

it('computes km/galón fill-to-fill across loads', function (): void {
    $resultado = calcular([
        carga(1, '2026-01-01', 1000, 50),
        carga(2, '2026-01-10', 1500, 45),
        carga(3, '2026-01-20', 1900, 48),
    ]);

    [$base, $segunda, $tercera] = $resultado['porCarga'];

    expect($base['rendimiento'])->toBeNull();
    expect($segunda['km_recorridos'])->toBe(500)
        ->and($segunda['rendimiento'])->toBe(11.11);
    expect($tercera['km_recorridos'])->toBe(400)
        ->and($tercera['rendimiento'])->toBe(8.33);
});

it('uses the period totals for the headline average', function (): void {
    $resultado = calcular([
        carga(1, '2026-01-01', 1000, 50, 700),
        carga(2, '2026-01-10', 1500, 45, 630),
        carga(3, '2026-01-20', 1900, 48, 720),
    ]);

    // km válidos = 500 + 400 = 900; galones válidos = 45 + 48 = 93.
    expect($resultado['resumen']['km_total'])->toBe(900)
        ->and($resultado['resumen']['rendimiento_promedio'])->toBe(9.68)
        ->and($resultado['resumen']['rendimiento_ultimo'])->toBe(8.33)
        ->and($resultado['resumen']['total_galones'])->toBe(143.0)
        ->and($resultado['resumen']['total_costo'])->toBe(2050.0)
        ->and($resultado['resumen']['costo_por_km'])->toBe(2.28);
});

it('sorts loads chronologically regardless of input order', function (): void {
    $resultado = calcular([
        carga(3, '2026-01-20', 1900, 48),
        carga(1, '2026-01-01', 1000, 50),
        carga(2, '2026-01-10', 1500, 45),
    ]);

    expect(array_column($resultado['porCarga'], 'id'))->toBe([1, 2, 3]);
});

it('flags a load whose odometer did not advance', function (): void {
    $resultado = calcular([
        carga(1, '2026-01-01', 1500, 45),
        carga(2, '2026-01-10', 1400, 40),
    ]);

    expect($resultado['porCarga'][1]['anomalia'])->toBeTrue()
        ->and($resultado['porCarga'][1]['km_recorridos'])->toBeNull()
        ->and($resultado['porCarga'][1]['rendimiento'])->toBeNull()
        ->and($resultado['resumen']['rendimiento_promedio'])->toBeNull();
});

it('flags an out-of-range efficiency but still shows the number', function (): void {
    $resultado = calcular([
        carga(1, '2026-01-01', 1000, 50),
        carga(2, '2026-01-10', 90000, 5),
    ]);

    expect($resultado['porCarga'][1]['anomalia'])->toBeTrue()
        ->and($resultado['porCarga'][1]['rendimiento'])->toBe(17800.0)
        // Excluida del promedio por ser anomalía.
        ->and($resultado['resumen']['rendimiento_promedio'])->toBeNull();
});

it('flags a load with zero gallons', function (): void {
    $resultado = calcular([
        carga(1, '2026-01-01', 1000, 50),
        carga(2, '2026-01-10', 1500, 0),
    ]);

    expect($resultado['porCarga'][1]['anomalia'])->toBeTrue()
        ->and($resultado['porCarga'][1]['rendimiento'])->toBeNull();
});

it('handles an empty set', function (): void {
    $resultado = calcular([]);

    expect($resultado['porCarga'])->toBe([])
        ->and($resultado['resumen']['total_cargas'])->toBe(0)
        ->and($resultado['resumen']['rendimiento_promedio'])->toBeNull()
        ->and($resultado['resumen']['costo_por_km'])->toBeNull();
});
