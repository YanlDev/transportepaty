<?php

use App\Services\Tracksolid\TracksolidLocation;

it('normalizes a real location payload', function (): void {
    $loc = TracksolidLocation::fromArray([
        'imei' => '860112070262441',
        'lat' => -15.492039,
        'lng' => -70.121224,
        'speed' => '42',
        'direction' => '180',
        'accStatus' => '1',
        'gpsTime' => '2026-06-19 15:33:41',
        'hbTime' => '2026-06-19 16:37:27',
    ]);

    expect($loc->imei())->toBe('860112070262441')
        ->and($loc->lat())->toBe(-15.492039)
        ->and($loc->lng())->toBe(-70.121224)
        ->and($loc->tienePosicion())->toBeTrue()
        ->and($loc->velocidad())->toBe(42)
        ->and($loc->rumbo())->toBe(180)
        ->and($loc->encendido())->toBeTrue()
        ->and($loc->estado())->toBe('en_movimiento')
        ->and($loc->fechaGps())->toBe('2026-06-19 15:33:41');
});

it('reports detenido when ignition is on but speed is zero', function (): void {
    $loc = TracksolidLocation::fromArray([
        'imei' => '1', 'lat' => -15.4, 'lng' => -70.1, 'speed' => '0', 'accStatus' => '1',
    ]);

    expect($loc->estado())->toBe('detenido');
});

it('reports apagado when ignition is off', function (): void {
    $loc = TracksolidLocation::fromArray([
        'imei' => '1', 'lat' => -15.4, 'lng' => -70.1, 'speed' => '30', 'accStatus' => '0',
    ]);

    expect($loc->estado())->toBe('apagado');
});

it('flags missing coordinates', function (): void {
    $loc = TracksolidLocation::fromArray(['imei' => '1', 'speed' => '0']);

    expect($loc->tienePosicion())->toBeFalse()
        ->and($loc->lat())->toBeNull();
});
