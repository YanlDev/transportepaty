<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCombustible;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Vehiculo;

it('exposes spanish labels for vehicle enums', function (): void {
    expect(EstadoVehiculo::EnMantenimiento->label())->toBe('En mantenimiento')
        ->and(TipoVehiculo::Suv->label())->toBe('SUV')
        ->and(TipoCombustible::Glp->label())->toBe('GLP');
});

it('builds a driver full name from first and last names', function (): void {
    $conductor = new Conductor(['nombres' => 'Juan', 'apellidos' => 'Pérez Quispe']);

    expect($conductor->nombre_completo)->toBe('Juan Pérez Quispe');
});

it('applies sensible defaults to a new vehicle', function (): void {
    $vehiculo = new Vehiculo;

    expect($vehiculo->estado)->toBe(EstadoVehiculo::Activo)
        ->and($vehiculo->tipo)->toBe(TipoVehiculo::Camioneta)
        ->and($vehiculo->combustible)->toBe(TipoCombustible::Diesel)
        ->and($vehiculo->kilometraje)->toBe(0);
});

it('casts status, type and fuel to enums', function (): void {
    $vehiculo = new Vehiculo(['estado' => 'en_mantenimiento']);

    expect($vehiculo->estado)->toBeInstanceOf(EstadoVehiculo::class)
        ->and($vehiculo->estado)->toBe(EstadoVehiculo::EnMantenimiento);
});
