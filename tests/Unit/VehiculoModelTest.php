<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Vehiculo;

it('exposes spanish labels for vehicle enums', function (): void {
    expect(EstadoVehiculo::EnMantenimiento->label())->toBe('En mantenimiento')
        ->and(TipoVehiculo::Tracto->label())->toBe('Tracto')
        ->and(TipoVehiculo::Carreta->label())->toBe('Carreta')
        ->and(TipoCaja::Automatica->label())->toBe('Automática');
});

it('builds a driver full name from first and last names', function (): void {
    $conductor = new Conductor(['nombres' => 'Juan', 'apellidos' => 'Pérez Quispe']);

    expect($conductor->nombre_completo)->toBe('Juan Pérez Quispe');
});

it('applies sensible defaults to a new vehicle', function (): void {
    $vehiculo = new Vehiculo;

    expect($vehiculo->estado)->toBe(EstadoVehiculo::Activo)
        ->and($vehiculo->tipo)->toBe(TipoVehiculo::Tracto);
});

it('casts status, type and gearbox to enums', function (): void {
    $vehiculo = new Vehiculo(['estado' => 'en_mantenimiento', 'caja' => 'mecanica']);

    expect($vehiculo->estado)->toBe(EstadoVehiculo::EnMantenimiento)
        ->and($vehiculo->caja)->toBe(TipoCaja::Mecanica);
});

it('knows only tractos carry a gearbox', function (): void {
    expect(TipoVehiculo::Tracto->tieneCaja())->toBeTrue()
        ->and(TipoVehiculo::Carreta->tieneCaja())->toBeFalse();
});

it('requires the soat only on tractos', function (): void {
    expect(TipoDocumento::Soat->aplicaA(TipoVehiculo::Tracto))->toBeTrue()
        ->and(TipoDocumento::Soat->aplicaA(TipoVehiculo::Carreta))->toBeFalse()
        ->and(TipoDocumento::Matpel->aplicaA(TipoVehiculo::Carreta))->toBeTrue();
});

it('lists one less required document for carretas', function (): void {
    expect(TipoVehiculo::Tracto->documentosExigibles())->toHaveCount(6)
        ->and(TipoVehiculo::Carreta->documentosExigibles())->toHaveCount(5);
});
