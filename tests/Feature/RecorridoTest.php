<?php

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

function adminRec(): User
{
    return User::factory()->create()->assignRole('admin');
}

it('redirects guests to login', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '111']);

    $this->get(route('vehiculos.recorrido', $vehiculo))->assertRedirect(route('login'));
});

it('rejects a vehicle without GPS', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => null]);

    actingAs(adminRec())
        ->getJson(route('vehiculos.recorrido', $vehiculo))
        ->assertStatus(422);
});

it('builds the route with distance and speed stats', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('trackList')->andReturn(collect([
            ['lat' => -15.4900, 'lng' => -70.1300, 'gpsTime' => '2026-06-24 08:00:00', 'gpsSpeed' => 0, 'direction' => 0],
            ['lat' => -15.4950, 'lng' => -70.1350, 'gpsTime' => '2026-06-24 08:10:00', 'gpsSpeed' => 40, 'direction' => 90],
            ['lat' => -15.5000, 'lng' => -70.1400, 'gpsTime' => '2026-06-24 08:30:00', 'gpsSpeed' => 20, 'direction' => 90],
        ]));
    });

    actingAs(adminRec())
        ->getJson(route('vehiculos.recorrido', $vehiculo).'?preset=ayer')
        ->assertOk()
        ->assertJsonPath('stats.velocidad_max', 40)
        ->assertJsonPath('stats.velocidad_prom', 30)
        ->assertJsonPath('stats.duracion_min', 30)
        ->assertJsonPath('stats.con_movimiento', true)
        ->assertJsonPath('stats.puntos', 3)
        ->assertJsonCount(3, 'puntos');
});

it('chunks multi-week ranges into 7-day windows', function (): void {
    // Fijamos la fecha a mitad de mes para que el preset "mes" abarque >7 días
    // de forma determinista (a inicios de mes abarcaría menos y no trocearía).
    $this->travelTo('2026-06-20 12:00:00');

    $vehiculo = Vehiculo::factory()->create(['imei' => '111']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        // "mes" abarca >7 días → debe llamarse track.list más de una vez.
        $mock->shouldReceive('trackList')->atLeast()->times(2)->andReturn(collect([]));
    });

    actingAs(adminRec())
        ->getJson(route('vehiculos.recorrido', $vehiculo).'?preset=mes')
        ->assertOk();
});

it('accepts a custom single day', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '111']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('trackList')
            ->once()
            ->with('111', '2026-06-20 00:00:00', '2026-06-20 23:59:59')
            ->andReturn(collect([
                ['lat' => -15.49, 'lng' => -70.13, 'gpsTime' => '2026-06-20 09:00:00', 'gpsSpeed' => 15],
            ]));
    });

    actingAs(adminRec())
        ->getJson(route('vehiculos.recorrido', $vehiculo).'?preset=personalizado&desde=2026-06-20')
        ->assertOk()
        ->assertJsonCount(1, 'puntos');
});

it('skips points without coordinates', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '111']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('trackList')->andReturn(collect([
            ['lat' => -15.49, 'lng' => -70.13, 'gpsTime' => '2026-06-24 08:00:00', 'gpsSpeed' => 10],
            ['lat' => 0, 'lng' => 0, 'gpsTime' => '2026-06-24 08:05:00', 'gpsSpeed' => 0],
        ]));
    });

    actingAs(adminRec())
        ->getJson(route('vehiculos.recorrido', $vehiculo).'?preset=hoy')
        ->assertJsonCount(1, 'puntos');
});

it('returns an error when the API fails', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '111']);

    test()->mock(TracksolidClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('trackList')->andThrow(new TracksolidException('rate limit', 1006));
    });

    actingAs(adminRec())
        ->getJson(route('vehiculos.recorrido', $vehiculo).'?preset=hoy')
        ->assertStatus(502);
});

it('forbids a driver from another vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '111']);

    actingAs(User::factory()->create()->assignRole('conductor'))
        ->getJson(route('vehiculos.recorrido', $vehiculo))
        ->assertForbidden();
});
