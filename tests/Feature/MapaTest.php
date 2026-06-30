<?php

use App\Models\Conductor;
use App\Models\User;
use App\Models\Vehiculo;
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

function adminMapa(): User
{
    return User::factory()->create()->assignRole('admin');
}

/**
 * @param  array<int, array<string, mixed>>  $locations
 */
function fakeUbicaciones(array $locations): void
{
    test()->mock(TracksolidClient::class, function (MockInterface $mock) use ($locations): void {
        $mock->shouldReceive('latestLocations')->andReturn(collect($locations));
    });
}

it('redirects guests to login', function (): void {
    $this->get(route('mapa'))->assertRedirect(route('login'));
});

it('plots vehicles that have a GPS position', function (): void {
    Vehiculo::factory()->create(['imei' => '860112070262441']);
    Vehiculo::factory()->create(['imei' => null]);

    fakeUbicaciones([
        [
            'imei' => '860112070262441',
            'lat' => -15.492039,
            'lng' => -70.121224,
            'speed' => '12',
            'accStatus' => '1',
            'gpsTime' => '2026-06-19 15:33:41',
        ],
    ]);

    actingAs(adminMapa())
        ->get(route('mapa'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('mapa/index')
            ->where('totalConGps', 1)
            ->has('marcadores', 1)
            ->where('marcadores.0.placa', Vehiculo::query()->whereNotNull('imei')->value('placa'))
            ->where('marcadores.0.estado', 'en_movimiento')
            ->where('marcadores.0.lat', -15.492039)
        );
});

it('excludes vehicles without a valid position', function (): void {
    Vehiculo::factory()->create(['imei' => '111']);

    fakeUbicaciones([
        ['imei' => '111', 'speed' => '0'], // sin lat/lng
    ]);

    actingAs(adminMapa())
        ->get(route('mapa'))
        ->assertInertia(fn ($page) => $page
            ->where('totalConGps', 1)
            ->has('marcadores', 0)
        );
});

it('shows an error when the location API fails', function (): void {
    Vehiculo::factory()->create(['imei' => '111']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('latestLocations')->andThrow(new TracksolidException('rate limit', 1006));
    });

    actingAs(adminMapa())
        ->get(route('mapa'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('error')
            ->has('marcadores', 0)
        );
});

it('only plots vehicles the driver can see', function (): void {
    $conductorUser = User::factory()->create()->assignRole('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $conductorUser->id]);

    Vehiculo::factory()->create(['imei' => 'AAA', 'conductor_id' => $conductor->id]);
    Vehiculo::factory()->create(['imei' => 'BBB']); // de otro

    fakeUbicaciones([
        ['imei' => 'AAA', 'lat' => -15.4, 'lng' => -70.1, 'accStatus' => '1', 'speed' => '0'],
        ['imei' => 'BBB', 'lat' => -15.5, 'lng' => -70.2, 'accStatus' => '1', 'speed' => '0'],
    ]);

    actingAs($conductorUser)
        ->get(route('mapa'))
        ->assertInertia(fn ($page) => $page
            ->where('totalConGps', 1)
            ->has('marcadores', 1)
        );
});
