<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoVehiculo;
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

function datosVehiculo(array $overrides = []): array
{
    return array_merge([
        'placa' => 'ABC-123',
        'marca' => 'INTERNATIONAL',
        'modelo' => 'LT625 6X4',
        'anio' => 2023,
        'tipo' => TipoVehiculo::Tracto->value,
        'estado' => EstadoVehiculo::Activo->value,
        'caja' => TipoCaja::Mecanica->value,
        'vin' => null,
        'numero_motor' => null,
        'color' => 'BLANCO',
        'ejes' => 3,
        'peso_neto' => 8000,
        'peso_bruto' => 27000,
        'carga_util' => 19000,
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
    Vehiculo::factory()->create(['placa' => 'XYZ-999', 'marca' => 'VOLVO']);
    Vehiculo::factory()->create(['placa' => 'ABC-111', 'marca' => 'SCANIA']);

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index', ['buscar' => 'XYZ']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('filters the list by tipo', function (): void {
    Vehiculo::factory()->create();
    Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index', ['tipo' => TipoVehiculo::Carreta->value]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('filters the list by caja', function (): void {
    Vehiculo::factory()->create(['caja' => TipoCaja::Mecanica]);
    Vehiculo::factory()->create(['caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index', ['caja' => TipoCaja::Mecanica->value]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.caja', TipoCaja::Mecanica->value)
        );
});

it('filters the list by units without a gearbox', function (): void {
    Vehiculo::factory()->create(['caja' => TipoCaja::Mecanica]);
    Vehiculo::factory()->create(['caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->carreta()->count(2)->create();

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index', ['caja' => 'sin_caja']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 2)
            ->where('vehiculos.data.0.caja', null)
        );
});

it('combines the tipo and caja filters', function (): void {
    Vehiculo::factory()->create(['caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->create(['caja' => TipoCaja::Mecanica]);
    Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index', [
            'tipo' => TipoVehiculo::Tracto->value,
            'caja' => TipoCaja::Automatica->value,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('offers every gearbox option plus the one for units without a motor', function (): void {
    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('cajas', 3)
            ->where('cajas.2.value', 'sin_caja')
        );
});

it('allows an admin to create a tracto', function (): void {
    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo())
        ->assertRedirect();

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'ABC-123',
        'tipo' => TipoVehiculo::Tracto->value,
        'caja' => TipoCaja::Mecanica->value,
    ]);
});

it('drops the caja when the vehicle is a carreta', function (): void {
    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo([
            'tipo' => TipoVehiculo::Carreta->value,
            'caja' => TipoCaja::Automatica->value,
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'ABC-123',
        'tipo' => TipoVehiculo::Carreta->value,
        'caja' => null,
    ]);
});

it('forbids a viewer from creating a vehicle', function (): void {
    actingAs(usuarioCon('visor'))
        ->post(route('vehiculos.store'), datosVehiculo())
        ->assertForbidden();

    $this->assertDatabaseCount('vehiculos', 0);
});

it('validates required fields when creating', function (): void {
    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo(['placa' => '']))
        ->assertSessionHasErrors('placa');
});

it('rejects a duplicate placa', function (): void {
    Vehiculo::factory()->create(['placa' => 'ABC-123']);

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo(['placa' => 'ABC-123']))
        ->assertSessionHasErrors('placa');
});

it('allows an admin to update a vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('admin'))
        ->put(route('vehiculos.update', $vehiculo), datosVehiculo([
            'placa' => $vehiculo->placa,
            'estado' => EstadoVehiculo::EnMantenimiento->value,
        ]))
        ->assertRedirect();

    expect($vehiculo->refresh())
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

it('offers the soat only for tractos', function (): void {
    $tracto = Vehiculo::factory()->create();
    $carreta = Vehiculo::factory()->carreta()->create();
    $admin = usuarioCon('admin');

    actingAs($admin)
        ->get(route('vehiculos.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/show')
            ->has('tiposDocumento', 6)
        );

    actingAs($admin)
        ->get(route('vehiculos.show', $carreta))
        ->assertInertia(fn (Assert $page) => $page->has('tiposDocumento', 5));
});
