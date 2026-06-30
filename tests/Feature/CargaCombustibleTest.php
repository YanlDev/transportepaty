<?php

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Storage::fake('public');
});

/**
 * Creates a driver user linked to a vehicle assigned to them.
 *
 * @return array{0: User, 1: Vehiculo}
 */
function conductorConVehiculo(): array
{
    $user = actorConRol('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);
    $vehiculo = Vehiculo::factory()->paraConductor($conductor)->create();

    return [$user, $vehiculo];
}

it('redirects guests to login', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $this->get(route('vehiculos.combustible.index', $vehiculo))
        ->assertRedirect(route('login'));
});

it('shows the fuel page with loads and summary to an admin', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    CargaCombustible::factory()->for($vehiculo)->count(2)->create();

    actingAs(actorConRol('admin'))
        ->get(route('vehiculos.combustible.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/combustible')
            ->has('cargas', 2)
            ->has('resumen')
            ->where('puede.registrar', true)
            ->where('puede.gestionar', true)
        );
});

it('computes the period average efficiency for processed loads', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    foreach ([['2026-01-01', 1000, 50], ['2026-01-10', 1500, 45], ['2026-01-20', 1900, 48]] as [$fecha, $odometro, $galones]) {
        CargaCombustible::factory()->for($vehiculo)->create([
            'fecha_carga' => $fecha,
            'odometro' => $odometro,
            'galones' => $galones,
        ]);
    }

    actingAs(actorConRol('admin'))
        ->get(route('vehiculos.combustible.index', $vehiculo))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.km_total', 900)
            ->where('resumen.rendimiento_promedio', 9.68)
        );
});

it('lets a driver view only their assigned vehicle', function (): void {
    [$user, $vehiculo] = conductorConVehiculo();

    actingAs($user)
        ->get(route('vehiculos.combustible.index', $vehiculo))
        ->assertSuccessful();
});

it('forbids a driver from another vehicle', function (): void {
    [$user] = conductorConVehiculo();
    $ajeno = Vehiculo::factory()->create();

    actingAs($user)
        ->get(route('vehiculos.combustible.index', $ajeno))
        ->assertForbidden();
});

it('lets a driver register a load by uploading photos', function (): void {
    [$user, $vehiculo] = conductorConVehiculo();

    actingAs($user)
        ->post(route('vehiculos.combustible.store', $vehiculo), [
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
            'odometro_foto' => UploadedFile::fake()->image('odometro.jpg'),
        ])
        ->assertRedirect();

    $carga = CargaCombustible::firstOrFail();

    expect($carga->vehiculo_id)->toBe($vehiculo->id)
        ->and($carga->registrada_por)->toBe($user->id)
        ->and($carga->procesada)->toBeFalse()
        ->and($carga->getFirstMedia('comprobante'))->not->toBeNull()
        ->and($carga->getFirstMedia('odometro_foto'))->not->toBeNull();
});

it('forbids a driver from registering on a vehicle that is not theirs', function (): void {
    [$user] = conductorConVehiculo();
    $ajeno = Vehiculo::factory()->create();

    actingAs($user)
        ->post(route('vehiculos.combustible.store', $ajeno), [
            'comprobante' => UploadedFile::fake()->image('c.jpg'),
            'odometro_foto' => UploadedFile::fake()->image('o.jpg'),
        ])
        ->assertForbidden();
});

it('requires either photos or data when registering', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.combustible.store', $vehiculo), [])
        ->assertSessionHasErrors('galones');
});

it('lets an admin register a load directly with data and no photos', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.combustible.store', $vehiculo), [
            'fecha_carga' => '2026-06-01',
            'odometro' => 12000,
            'galones' => 8,
            'costo_total' => 160,
        ])
        ->assertRedirect();

    $carga = CargaCombustible::firstOrFail();

    expect($carga->procesada)->toBeTrue()
        ->and((int) $carga->odometro)->toBe(12000)
        ->and((float) $carga->galones)->toBe(8.0)
        ->and((float) $carga->precio_por_galon)->toBe(20.0);
});

it('updates a GPS-less vehicle odometer when a load reads higher', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => null, 'kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.combustible.store', $vehiculo), [
            'fecha_carga' => '2026-06-01',
            'odometro' => 12000,
            'galones' => 8,
            'costo_total' => 160,
        ])
        ->assertRedirect();

    expect((int) $vehiculo->fresh()->kilometraje)->toBe(12000);
});

it('does not lower a GPS-less vehicle odometer when a load reads lower', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => null, 'kilometraje' => 15000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.combustible.store', $vehiculo), [
            'fecha_carga' => '2026-06-01',
            'odometro' => 12000,
            'galones' => 8,
            'costo_total' => 160,
        ])
        ->assertRedirect();

    expect((int) $vehiculo->fresh()->kilometraje)->toBe(15000);
});

it('does not touch a GPS vehicle odometer from a load', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => '860112070376688', 'kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.combustible.store', $vehiculo), [
            'fecha_carga' => '2026-06-01',
            'odometro' => 99000,
            'galones' => 8,
            'costo_total' => 160,
        ])
        ->assertRedirect();

    expect((int) $vehiculo->fresh()->kilometraje)->toBe(10000);
});

it('requires the odometer for a direct registration', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.combustible.store', $vehiculo), [
            'galones' => 8,
        ])
        ->assertSessionHasErrors('odometro');
});

it('lists only unprocessed loads in the admin inbox', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    CargaCombustible::factory()->for($vehiculo)->pendiente()->count(2)->create();
    CargaCombustible::factory()->for($vehiculo)->create(); // procesada → no aparece

    actingAs(actorConRol('admin'))
        ->get(route('combustible.pendientes'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('combustible/pendientes')
            ->has('cargas', 2)
        );
});

it('forbids non-admins from the pending inbox', function (): void {
    actingAs(actorConRol('visor'))->get(route('combustible.pendientes'))->assertForbidden();
    actingAs(actorConRol('conductor'))->get(route('combustible.pendientes'))->assertForbidden();
});

it('lets an admin process a pending load and derives the price per gallon', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $carga = CargaCombustible::factory()->for($vehiculo)->pendiente()->create();

    actingAs(actorConRol('admin'))
        ->put(route('vehiculos.combustible.update', [$vehiculo, $carga]), [
            'fecha_carga' => '2026-02-01',
            'odometro' => 25000,
            'galones' => 10,
            'costo_total' => 160,
            'observaciones' => 'Grifo Primax',
        ])
        ->assertRedirect();

    $carga->refresh();

    expect($carga->procesada)->toBeTrue()
        ->and((int) $carga->odometro)->toBe(25000)
        ->and((float) $carga->galones)->toBe(10.0)
        ->and((float) $carga->precio_por_galon)->toBe(16.0);
});

it('forbids a driver from processing a load', function (): void {
    [$user, $vehiculo] = conductorConVehiculo();
    $carga = CargaCombustible::factory()->for($vehiculo)->pendiente()->create();

    actingAs($user)
        ->put(route('vehiculos.combustible.update', [$vehiculo, $carga]), [
            'fecha_carga' => '2026-02-01',
            'odometro' => 25000,
            'galones' => 10,
        ])
        ->assertForbidden();
});

it('validates gallons greater than zero when processing', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $carga = CargaCombustible::factory()->for($vehiculo)->pendiente()->create();

    actingAs(actorConRol('admin'))
        ->put(route('vehiculos.combustible.update', [$vehiculo, $carga]), [
            'fecha_carga' => '2026-02-01',
            'odometro' => 25000,
            'galones' => 0,
        ])
        ->assertSessionHasErrors('galones');
});

it('returns 404 when the load does not belong to the vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();
    $carga = CargaCombustible::factory()->for($otro)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('vehiculos.combustible.destroy', [$vehiculo, $carga]))
        ->assertNotFound();
});

it('lists the driver assigned vehicles on the quick-register page', function (): void {
    [$user, $vehiculo] = conductorConVehiculo();
    Vehiculo::factory()->create(); // de otro conductor, no debe aparecer

    actingAs($user)
        ->get(route('combustible.rapido'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('combustible/rapido')
            ->has('vehiculos', 1)
            ->where('vehiculos.0.id', $vehiculo->id)
        );
});

it('shows no vehicles on the quick-register page when none are assigned', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('combustible.rapido'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos', 0));
});

it('lets an admin delete a load but forbids a driver', function (): void {
    [$user, $vehiculo] = conductorConVehiculo();
    $carga = CargaCombustible::factory()->for($vehiculo)->create();

    actingAs($user)
        ->delete(route('vehiculos.combustible.destroy', [$vehiculo, $carga]))
        ->assertForbidden();

    actingAs(actorConRol('admin'))
        ->delete(route('vehiculos.combustible.destroy', [$vehiculo, $carga]))
        ->assertRedirect();

    $this->assertDatabaseMissing('cargas_combustible', ['id' => $carga->id]);
});
