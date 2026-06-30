<?php

use App\Enums\TipoVehiculo;
use App\Models\CargaCombustible;
use App\Models\Mantenimiento;
use App\Models\PlantillaMantenimiento;
use App\Models\Vehiculo;
use App\Services\Mantenimiento\PlanMantenimientoService;

function plan(): PlanMantenimientoService
{
    return app(PlanMantenimientoService::class);
}

/**
 * Records a maintenance with one item tied to the given template.
 */
function registrarServicio(Vehiculo $vehiculo, PlantillaMantenimiento $plantilla, int $odometro, string $fecha): Mantenimiento
{
    $mantenimiento = Mantenimiento::factory()->for($vehiculo)->create([
        'odometro' => $odometro,
        'fecha_realizado' => $fecha,
    ]);

    $mantenimiento->items()->create([
        'plantilla_id' => $plantilla->id,
        'nombre' => $plantilla->nombre,
        'tipo_mantenimiento' => $plantilla->tipo_mantenimiento,
    ]);

    return $mantenimiento;
}

it('uses the GPS kilometraje when the vehicle has GPS', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '123456', 'kilometraje' => 50000]);
    CargaCombustible::factory()->for($vehiculo)->create(['odometro' => 99999]);

    expect(plan()->odometroVigente($vehiculo))->toBe(50000);
});

it('uses the max known reading when there is no GPS', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => null, 'kilometraje' => 50000]);
    CargaCombustible::factory()->for($vehiculo)->create(['odometro' => 52000]);

    expect(plan()->odometroVigente($vehiculo))->toBe(52000);
});

it('resolves the template cascade, preferring the specific over the generic', function (): void {
    $vehiculo = Vehiculo::factory()->create(['marca' => 'Toyota', 'modelo' => 'Hilux']);

    PlantillaMantenimiento::factory()->create(['marca' => 'Toyota', 'modelo' => 'Hilux']);
    PlantillaMantenimiento::factory()->create(['marca' => null, 'modelo' => null, 'tipo_vehiculo' => null]);

    $plantillas = plan()->plantillasParaVehiculo($vehiculo);

    expect($plantillas)->toHaveCount(1)
        ->and($plantillas->first()->modelo)->toBe('Hilux');
});

it('falls back to the generic template when nothing matches', function (): void {
    $vehiculo = Vehiculo::factory()->create(['marca' => 'MarcaRara', 'modelo' => 'XYZ', 'tipo' => TipoVehiculo::Auto]);
    PlantillaMantenimiento::factory()->create(['marca' => null, 'modelo' => null, 'tipo_vehiculo' => null]);

    expect(plan()->plantillasParaVehiculo($vehiculo))->toHaveCount(1);
});

it('marks a service with no history as sin_historial', function (): void {
    $vehiculo = Vehiculo::factory()->create(['marca' => 'Toyota', 'modelo' => 'Hilux']);
    PlantillaMantenimiento::factory()->create(['marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000]);

    $proximos = plan()->proximosVencimientos($vehiculo);

    expect($proximos[0]['status'])->toBe('sin_historial');
});

it('computes a km-based due status from the last service', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => 'x', 'kilometraje' => 14800, 'marca' => 'Toyota', 'modelo' => 'Hilux']);
    $plantilla = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000, 'intervalo_meses' => null,
    ]);
    registrarServicio($vehiculo, $plantilla, 10000, now()->toDateString());

    $proximos = plan()->proximosVencimientos($vehiculo);

    expect($proximos[0]['proximo_odometro'])->toBe(15000)
        ->and($proximos[0]['restante_km'])->toBe(200)
        ->and($proximos[0]['status'])->toBe('proximo');
});

it('computes a month-based due status (regression for diffInDays)', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => 'x', 'kilometraje' => 10000, 'marca' => 'Toyota', 'modelo' => 'Hilux']);
    $plantilla = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => null, 'intervalo_meses' => 6,
    ]);
    // Servicio hace 6 meses y 5 días → vencido por tiempo.
    registrarServicio($vehiculo, $plantilla, 10000, now()->subMonths(6)->subDays(5)->toDateString());

    $proximos = plan()->proximosVencimientos($vehiculo);

    expect($proximos[0]['status'])->toBe('vencido')
        ->and($proximos[0]['restante_dias'])->toBeLessThanOrEqual(0);
});

it('takes the most urgent of km and months', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => 'x', 'kilometraje' => 10100, 'marca' => 'Toyota', 'modelo' => 'Hilux']);
    $plantilla = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000, 'intervalo_meses' => 6,
    ]);
    // Por km falta mucho (próximo 15000, restante 4900 = ok) pero por tiempo está vencido.
    registrarServicio($vehiculo, $plantilla, 10000, now()->subMonths(7)->toDateString());

    expect(plan()->proximosVencimientos($vehiculo)[0]['status'])->toBe('vencido');
});

it('counts services by state for the plan summary', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => 'x', 'kilometraje' => 10100, 'marca' => 'Toyota', 'modelo' => 'Hilux']);

    $aceite = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000, 'intervalo_meses' => null, 'orden' => 1,
    ]);
    $frenos = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 1000, 'intervalo_meses' => null, 'orden' => 2,
    ]);
    registrarServicio($vehiculo, $aceite, 9500, now()->toDateString());   // próximo 14500, restante 4400 = ok
    registrarServicio($vehiculo, $frenos, 5000, now()->toDateString());   // próximo 6000, restante -4100 = vencido

    $conteo = plan()->conteoEstados(plan()->proximosVencimientos($vehiculo));

    expect($conteo['vencido'])->toBe(1)
        ->and($conteo['al_dia'])->toBe(1);
});

it('sums the year cost of ownership (fuel + maintenance)', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $anio = (int) now()->year;

    CargaCombustible::factory()->for($vehiculo)->create([
        'fecha_carga' => now(),
        'costo_total' => 800,
    ]);
    Mantenimiento::factory()->for($vehiculo)->create([
        'fecha_realizado' => now(),
        'costo_total' => 200,
    ]);
    // De otro año: no debe contar.
    Mantenimiento::factory()->for($vehiculo)->create([
        'fecha_realizado' => now()->subYears(2),
        'costo_total' => 999,
    ]);

    $costos = plan()->costosAnio($vehiculo, $anio);

    expect($costos['total'])->toBe(1000.0)
        ->and($costos['categorias'])->toHaveCount(2)
        ->and(collect($costos['categorias'])->firstWhere('clave', 'combustible')['monto'])->toBe(800.0)
        ->and(collect($costos['categorias'])->firstWhere('clave', 'mantenimiento')['monto'])->toBe(200.0);
});

it('aggregates maintenance statistics', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    Mantenimiento::factory()->for($vehiculo)->create(['costo_total' => 100, 'fecha_realizado' => now()->subMonth()])
        ->items()->create(['nombre' => 'Cambio de aceite', 'tipo_mantenimiento' => 'aceite']);
    Mantenimiento::factory()->for($vehiculo)->create(['costo_total' => 300, 'fecha_realizado' => now()])
        ->items()->create(['nombre' => 'Cambio de aceite', 'tipo_mantenimiento' => 'aceite']);

    $stats = plan()->estadisticas($vehiculo);

    expect($stats['total_mantenimientos'])->toBe(2)
        ->and($stats['total_gastado'])->toBe(400.0)
        ->and($stats['ultimo_costo'])->toBe(300.0)
        ->and($stats['costo_promedio'])->toBe(200.0)
        ->and($stats['mas_comun'])->toBe('Cambio de aceite');
});
