<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCombustible;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vehiculo;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function usuarioCon(string $rol): User
{
    return User::factory()->create()->assignRole($rol);
}

function datosVehiculo(Sucursal $sucursal, array $overrides = []): array
{
    return array_merge([
        'sucursal_id' => $sucursal->id,
        'conductor_id' => null,
        'placa' => 'ABC-123',
        'marca' => 'Toyota',
        'modelo' => 'Hilux',
        'anio' => 2023,
        'color' => 'Blanco',
        'numero_serie' => null,
        'numero_motor' => null,
        'tipo' => TipoVehiculo::Camioneta->value,
        'combustible' => TipoCombustible::Diesel->value,
        'estado' => EstadoVehiculo::Activo->value,
        'kilometraje' => 1500,
        'fecha_adquisicion' => null,
        'observaciones' => null,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('vehiculos.index'))->assertRedirect(route('login'));
});

it('lets authenticated users see the list', function (): void {
    Vehiculo::factory()->count(3)->create();

    actingAs(usuarioCon('visor'))
        ->get(route('vehiculos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/index')
            ->has('vehiculos.data', 3)
        );
});

it('filters the list by search term', function (): void {
    $sucursal = Sucursal::factory()->create();
    Vehiculo::factory()->for($sucursal)->create(['placa' => 'XYZ-999', 'marca' => 'Nissan']);
    Vehiculo::factory()->for($sucursal)->create(['placa' => 'ABC-111', 'marca' => 'Toyota']);

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index', ['buscar' => 'XYZ']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('allows an admin to create a vehicle', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal))
        ->assertRedirect();

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'ABC-123',
        'sucursal_id' => $sucursal->id,
    ]);
});

it('forbids a viewer from creating a vehicle', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(usuarioCon('visor'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal))
        ->assertForbidden();

    $this->assertDatabaseCount('vehiculos', 0);
});

it('validates required fields when creating', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal, ['placa' => '']))
        ->assertSessionHasErrors('placa');
});

it('rejects a duplicate placa', function (): void {
    $sucursal = Sucursal::factory()->create();
    Vehiculo::factory()->for($sucursal)->create(['placa' => 'ABC-123']);

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal, ['placa' => 'ABC-123']))
        ->assertSessionHasErrors('placa');
});

it('registers a vehicle with a GPS imei from the normal form', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal, ['imei' => '860112070376688']))
        ->assertRedirect();

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'ABC-123',
        'imei' => '860112070376688',
    ]);
});

it('rejects a duplicate imei', function (): void {
    $sucursal = Sucursal::factory()->create();
    Vehiculo::factory()->for($sucursal)->create(['imei' => '860112070376688']);

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal, ['placa' => 'NEW-001', 'imei' => '860112070376688']))
        ->assertSessionHasErrors('imei');
});

it('rejects a driver from another branch', function (): void {
    $sucursal = Sucursal::factory()->create();
    $otraSucursal = Sucursal::factory()->create();
    $conductor = Conductor::factory()->for($otraSucursal)->create();

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo($sucursal, ['conductor_id' => $conductor->id]))
        ->assertSessionHasErrors('conductor_id');
});

it('allows an admin to update a vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('admin'))
        ->put(route('vehiculos.update', $vehiculo), datosVehiculo($vehiculo->sucursal, [
            'placa' => $vehiculo->placa,
            'kilometraje' => 99000,
            'estado' => EstadoVehiculo::EnMantenimiento->value,
        ]))
        ->assertRedirect();

    expect($vehiculo->refresh())
        ->kilometraje->toBe(99000)
        ->estado->toBe(EstadoVehiculo::EnMantenimiento);
});

it('soft deletes a vehicle as admin', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('admin'))
        ->delete(route('vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('vehiculos.index'));

    $this->assertSoftDeleted($vehiculo);
});

it('forbids a viewer from deleting a vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('visor'))
        ->delete(route('vehiculos.destroy', $vehiculo))
        ->assertForbidden();

    $this->assertNotSoftDeleted($vehiculo);
});

it('shows a driver only the vehicles assigned to them', function (): void {
    $user = usuarioCon('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);

    Vehiculo::factory()->create([
        'conductor_id' => $conductor->id,
        'sucursal_id' => $conductor->sucursal_id,
    ]);
    Vehiculo::factory()->count(2)->create();

    actingAs($user)
        ->get(route('vehiculos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('lets a driver view a vehicle assigned to them', function (): void {
    $user = usuarioCon('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);
    $vehiculo = Vehiculo::factory()->create([
        'conductor_id' => $conductor->id,
        'sucursal_id' => $conductor->sucursal_id,
    ]);

    actingAs($user)
        ->get(route('vehiculos.show', $vehiculo))
        ->assertSuccessful();
});

it('forbids a driver from viewing a vehicle not assigned to them', function (): void {
    $user = usuarioCon('conductor');
    Conductor::factory()->create(['user_id' => $user->id]);
    $vehiculo = Vehiculo::factory()->create();

    actingAs($user)
        ->get(route('vehiculos.show', $vehiculo))
        ->assertForbidden();
});
