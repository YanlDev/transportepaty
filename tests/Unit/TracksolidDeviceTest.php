<?php

use App\Services\Tracksolid\TracksolidDevice;

it('normalizes raw device fields from aliases', function (): void {
    $device = TracksolidDevice::fromArray([
        'imei' => '868120303456789',
        'mcType' => 'JC181',
        'vehicleNumber' => 'ABC-123',
        'carFrame' => 'VIN123456789',
        'vehicleBrand' => 'Toyota',
        'vehicleModels' => 'Hilux',
        'engineNumber' => 'ENG-001',
        'currentMileage' => '12345.7',
        'enabledFlag' => 1,
    ]);

    expect($device->imei())->toBe('868120303456789')
        ->and($device->modelo())->toBe('JC181')
        ->and($device->placa())->toBe('ABC-123')
        ->and($device->vin())->toBe('VIN123456789')
        ->and($device->marca())->toBe('Toyota')
        ->and($device->modeloVehiculo())->toBe('Hilux')
        ->and($device->numeroMotor())->toBe('ENG-001')
        ->and($device->kilometraje())->toBe(12346)
        ->and($device->activo())->toBeTrue()
        ->and($device->esDashcam())->toBeTrue();
});

it('builds vehiculo attributes with only the filled values', function (): void {
    $device = TracksolidDevice::fromArray([
        'imei' => '123',
        'vehicleNumber' => 'XYZ-999',
        'vehicleBrand' => 'Nissan',
    ]);

    expect($device->toVehiculoAttributes())->toBe([
        'placa' => 'XYZ-999',
        'marca' => 'Nissan',
    ]);
});

it('returns null for missing fields and is not a dashcam for plain trackers', function (): void {
    $device = TracksolidDevice::fromArray(['imei' => '999', 'mcType' => 'GT06']);

    expect($device->placa())->toBeNull()
        ->and($device->kilometraje())->toBeNull()
        ->and($device->esDashcam())->toBeFalse();
});
