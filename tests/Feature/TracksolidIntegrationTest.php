<?php

use App\Models\Conductor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Tracksolid\RecorridoService;
use App\Services\Tracksolid\TracksolidClient;
use App\Services\Tracksolid\TracksolidException;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function adminGps(): User
{
    return User::factory()->create()->assignRole('admin');
}

/**
 * @param  array<int, array<string, mixed>>  $devices
 */
function fakeTracksolid(array $devices = [], ?array $detail = null): void
{
    test()->mock(TracksolidClient::class, function (MockInterface $mock) use ($devices, $detail): void {
        $mock->shouldReceive('listDevices')->andReturn(collect($devices));
        $mock->shouldReceive('liveVideoUrl')->andReturn('https://us.tracksolidpro.com/video/test');
        $mock->shouldReceive('historicVideoUrl')->andReturn('https://us.tracksolidpro.com/video/hist');

        if ($detail !== null) {
            $mock->shouldReceive('deviceDetail')->andReturn($detail);
        }
    });
}

it('blocks non-admins from the devices screen', function (): void {
    fakeTracksolid();

    actingAs(User::factory()->create()->assignRole('visor'))
        ->get(route('integraciones.tracksolid.index'))
        ->assertForbidden();
});

it('lists devices and flags the one already linked to a vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '868000000000001']);

    fakeTracksolid([
        ['imei' => '868000000000001', 'mcType' => 'JC181', 'vehicleNumber' => 'ABC-123'],
        ['imei' => '868000000000002', 'mcType' => 'JC181', 'vehicleNumber' => 'NEW-555'],
    ]);

    actingAs(adminGps())
        ->get(route('integraciones.tracksolid.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('integraciones/tracksolid')
            ->has('dispositivos', 2)
            ->where('dispositivos.0.vehiculo.id', $vehiculo->id)
            ->where('dispositivos.1.vehiculo', null)
        );
});

it('shows an error message when the API fails', function (): void {
    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('listDevices')->andThrow(new TracksolidException('rate limit', 1006));
    });

    actingAs(adminGps())
        ->get(route('integraciones.tracksolid.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('error')
            ->has('dispositivos', 0)
        );
});

it('links a device to an existing vehicle', function (): void {
    fakeTracksolid();
    $vehiculo = Vehiculo::factory()->create(['imei' => null]);

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.vincular'), [
            'imei' => '868000000000009',
            'vehiculo_id' => $vehiculo->id,
        ])
        ->assertRedirect();

    expect($vehiculo->fresh()->imei)->toBe('868000000000009');
});

it('rejects linking a device that is already in use', function (): void {
    fakeTracksolid();
    Vehiculo::factory()->create(['imei' => '868000000000009']);
    $otro = Vehiculo::factory()->create(['imei' => null]);

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.vincular'), [
            'imei' => '868000000000009',
            'vehiculo_id' => $otro->id,
        ]);

    expect($otro->fresh()->imei)->toBeNull();
});

it('imports a device as a new vehicle using its detail', function (): void {
    $sucursal = Sucursal::factory()->create();

    fakeTracksolid(detail: [
        'imei' => '868000000000010',
        'vehicleNumber' => 'IMP-777',
        'vehicleBrand' => 'Toyota',
        'vehicleModels' => 'Hilux',
        'carFrame' => 'VINIMPORT01',
        'currentMileage' => '54000',
    ]);

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.importar'), [
            'imei' => '868000000000010',
            'sucursal_id' => $sucursal->id,
        ])
        ->assertRedirect();

    $vehiculo = Vehiculo::query()->where('imei', '868000000000010')->firstOrFail();

    expect($vehiculo->placa)->toBe('IMP-777')
        ->and($vehiculo->marca)->toBe('Toyota')
        ->and($vehiculo->numero_serie)->toBe('VINIMPORT01')
        ->and($vehiculo->kilometraje)->toBe(54000)
        ->and($vehiculo->sucursal_id)->toBe($sucursal->id);
});

it('advances the odometer by the track distance and anchors to the last GPS point', function (): void {
    $vehiculo = Vehiculo::factory()->create([
        'imei' => '868000000000011',
        'kilometraje' => 50000,
        'odometro_sincronizado_en' => now()->subHour(),
    ]);

    test()->mock(RecorridoService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('paraRango')->once()->andReturn([
            'puntos' => [
                ['lat' => -15.40, 'lng' => -70.10, 'hora' => '2026-06-30 10:00:00', 'velocidad' => 20, 'rumbo' => 0],
                ['lat' => -15.50, 'lng' => -70.20, 'hora' => '2026-06-30 10:30:00', 'velocidad' => 40, 'rumbo' => 0],
            ],
            'stats' => [
                'distancia_km' => 10.15,
                'duracion_min' => 30,
                'velocidad_prom' => 20,
                'velocidad_max' => 60,
                'puntos' => 2,
                'con_movimiento' => true,
            ],
        ]);
    });

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.sincronizar', $vehiculo))
        ->assertRedirect();

    $fresh = $vehiculo->fresh();
    // 50000 + round(10.15) = 50010; el ancla queda en el último punto GPS.
    expect($fresh->kilometraje)->toBe(50010)
        ->and($fresh->odometro_sincronizado_en->toDateTimeString())->toBe('2026-06-30 10:30:00');
});

it('leaves the odometer unchanged when there is no new track distance', function (): void {
    $vehiculo = Vehiculo::factory()->create([
        'imei' => '868000000000011',
        'kilometraje' => 50000,
        'odometro_sincronizado_en' => now()->subHour(),
    ]);

    test()->mock(RecorridoService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('paraRango')->once()->andReturn([
            'puntos' => [],
            'stats' => [
                'distancia_km' => 0.0,
                'duracion_min' => 0,
                'velocidad_prom' => 0,
                'velocidad_max' => 0,
                'puntos' => 0,
                'con_movimiento' => false,
            ],
        ]);
    });

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.sincronizar', $vehiculo))
        ->assertRedirect();

    expect($vehiculo->fresh()->kilometraje)->toBe(50000);
});

it('calibrates the odometer to the entered value', function (): void {
    $vehiculo = Vehiculo::factory()->create([
        'imei' => '868000000000020',
        'kilometraje' => 234,
    ]);

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.calibrar', $vehiculo), ['kilometraje' => 50000])
        ->assertRedirect();

    $fresh = $vehiculo->fresh();
    expect($fresh->kilometraje)->toBe(50000)
        ->and($fresh->km_calibrado_en)->not->toBeNull()
        ->and($fresh->odometro_sincronizado_en)->not->toBeNull();
});

it('forbids non-admins from calibrating', function (): void {
    fakeTracksolid();
    $vehiculo = Vehiculo::factory()->create(['imei' => '868000000000021']);

    actingAs(User::factory()->create()->assignRole('visor'))
        ->post(route('integraciones.tracksolid.calibrar', $vehiculo), ['kilometraje' => 50000])
        ->assertForbidden();
});

it('refuses to sync a vehicle without a device', function (): void {
    fakeTracksolid();
    $vehiculo = Vehiculo::factory()->create(['imei' => null]);

    actingAs(adminGps())
        ->post(route('integraciones.tracksolid.sincronizar', $vehiculo))
        ->assertRedirect();

    expect($vehiculo->fresh()->kilometraje)->toBe($vehiculo->kilometraje);
});

it('returns a live camera url for the requested channel', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('liveVideoUrl')
            ->with('860112070376688', 2)
            ->andReturn('https://us.tracksolidpro.com/tools/device/video/abc');
    });

    actingAs(adminGps())
        ->getJson(route('integraciones.tracksolid.camara', $vehiculo).'?modo=vivo&channel=2')
        ->assertOk()
        ->assertJson(['url' => 'https://us.tracksolidpro.com/tools/device/video/abc']);
});

it('returns the historic console url in historic mode', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('historicVideoUrl')
            ->with('860112070376688')
            ->andReturn('https://us.tracksolidpro.com/tools/device/video/hist');
    });

    actingAs(adminGps())
        ->getJson(route('integraciones.tracksolid.camara', $vehiculo).'?modo=historico')
        ->assertOk()
        ->assertJson(['url' => 'https://us.tracksolidpro.com/tools/device/video/hist']);
});

it('renders the live camera page for admins', function (): void {
    fakeTracksolid();
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688']);

    actingAs(adminGps())
        ->get(route('integraciones.tracksolid.camaras', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('vehiculos/camara')
            ->where('vehiculo.id', $vehiculo->id)
        );
});

it('lets a viewer open any camera page', function (): void {
    fakeTracksolid();
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688']);

    actingAs(User::factory()->create()->assignRole('visor'))
        ->get(route('integraciones.tracksolid.camaras', $vehiculo))
        ->assertSuccessful();
});

it('lets a driver open the camera of their own vehicle', function (): void {
    fakeTracksolid();
    $user = User::factory()->create()->assignRole('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);
    $vehiculo = Vehiculo::factory()->for($conductor)->create(['imei' => '860112070376688']);

    actingAs($user)
        ->get(route('integraciones.tracksolid.camaras', $vehiculo))
        ->assertSuccessful();
});

it('forbids a driver from the camera of a vehicle that is not theirs', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688']);

    actingAs(User::factory()->create()->assignRole('conductor'))
        ->getJson(route('integraciones.tracksolid.camara', $vehiculo))
        ->assertForbidden();
});

it('rejects the camera for a vehicle without GPS', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => null]);

    actingAs(adminGps())
        ->getJson(route('integraciones.tracksolid.camara', $vehiculo))
        ->assertStatus(422);
});

it('unlinks a device from a vehicle', function (): void {
    fakeTracksolid();
    $vehiculo = Vehiculo::factory()->create(['imei' => '868000000000012']);

    actingAs(adminGps())
        ->delete(route('integraciones.tracksolid.desvincular', $vehiculo))
        ->assertRedirect();

    expect($vehiculo->fresh()->imei)->toBeNull();
});
