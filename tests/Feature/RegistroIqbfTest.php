<?php

use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('flags in the units list which ones can carry bienes fiscalizados', function (): void {
    Vehiculo::factory()->create(['placa' => 'BJF-934', 'iqbf' => true]);
    Vehiculo::factory()->create(['placa' => 'CAL-778', 'iqbf' => false]);

    actingAs(actorConRol('visor'))
        ->get(route('tractos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.placa', 'BJF-934')
            ->where('vehiculos.data.0.iqbf', true)
            ->where('vehiculos.data.1.iqbf', false));
});

it('filters the units list by the bienes fiscalizados registry', function (string $bf, string $placa): void {
    Vehiculo::factory()->create(['placa' => 'BJF-934', 'iqbf' => true]);
    Vehiculo::factory()->create(['placa' => 'CAL-778', 'iqbf' => false]);

    actingAs(actorConRol('visor'))
        ->get(route('tractos.index', ['bf' => $bf]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.placa', $placa)
            ->where('filtros.bf', $bf));
})->with([
    'inscritas' => ['si', 'BJF-934'],
    'no inscritas' => ['no', 'CAL-778'],
]);

it('flags in the conductores list who can carry bienes fiscalizados', function (): void {
    Conductor::factory()->create(['iqbf' => true]);

    actingAs(actorConRol('visor'))
        ->get(route('conductores.index'))
        ->assertInertia(fn (Assert $page) => $page->where('conductores.data.0.iqbf', true));
});

it('flags in the GR table the tracto, carreta and chofer inscribed in the registry', function (): void {
    $tracto = Vehiculo::factory()->create(['iqbf' => true]);
    $carreta = Vehiculo::factory()->carreta()->create(['iqbf' => false]);
    $conductor = Conductor::factory()->create(['iqbf' => true]);
    Viaje::factory()->create([
        'placa_tracto' => $tracto->placa,
        'tracto_id' => $tracto->id,
        'placa_carreta' => $carreta->placa,
        'carreta_id' => $carreta->id,
        'conductor_id' => $conductor->id,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajes.data.0.tracto_iqbf', true)
            ->where('viajes.data.0.carreta_iqbf', false)
            ->where('viajes.data.0.conductor_iqbf', true));
});

it('marks only the units and conductores of the SUNAT list that match the padrón', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'BJF-934']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VFJ972']);
    $fuera = Vehiculo::factory()->create(['placa' => 'CAL-778']);
    $conCero = Conductor::factory()->create(['documento' => '02411719']);
    $sinCero = Conductor::factory()->create(['documento' => '2381183']);
    $noInscrito = Conductor::factory()->create(['documento' => '45154573']);

    $migracion = require database_path('migrations/2026_11_10_202012_marca_las_unidades_y_conductores_inscritos_en_el_iqbf.php');
    $migracion->up();

    expect($tracto->fresh()->iqbf)->toBeTrue()
        ->and($carreta->fresh()->iqbf)->toBeTrue()
        ->and($fuera->fresh()->iqbf)->toBeFalse()
        ->and($conCero->fresh()->iqbf_desde->toDateString())->toBe('2021-07-12')
        ->and($sinCero->fresh()->iqbf)->toBeTrue()
        ->and($noInscrito->fresh()->iqbf)->toBeFalse();
});
