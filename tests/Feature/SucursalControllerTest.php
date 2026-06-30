<?php

use App\Models\Conductor;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosSucursal(array $overrides = []): array
{
    return array_merge([
        'nombre' => 'Sucursal Lima Norte',
        'codigo' => 'SUC-100',
        'direccion' => 'Av. Principal 123',
        'ciudad' => 'Lima',
        'telefono' => '+51 999 888 777',
        'activa' => true,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('sucursales.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list with counts', function (): void {
    $sucursal = Sucursal::factory()->create();
    Vehiculo::factory()->count(2)->for($sucursal)->create();

    actingAs(actorConRol('visor'))
        ->get(route('sucursales.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sucursales/index')
            ->has('sucursales.data', 1)
            ->where('sucursales.data.0.vehiculos_count', 2)
        );
});

it('forbids drivers from the list', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('sucursales.index'))
        ->assertForbidden();
});

it('allows an admin to create a sucursal', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('sucursales.store'), datosSucursal())
        ->assertRedirect(route('sucursales.index'));

    $this->assertDatabaseHas('sucursales', ['codigo' => 'SUC-100', 'nombre' => 'Sucursal Lima Norte']);
});

it('forbids a viewer from creating a sucursal', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('sucursales.store'), datosSucursal())
        ->assertForbidden();

    $this->assertDatabaseCount('sucursales', 0);
});

it('validates required fields when creating', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('sucursales.store'), datosSucursal(['nombre' => '', 'codigo' => '']))
        ->assertSessionHasErrors(['nombre', 'codigo']);
});

it('rejects a duplicate codigo', function (): void {
    Sucursal::factory()->create(['codigo' => 'SUC-100']);

    actingAs(actorConRol('admin'))
        ->post(route('sucursales.store'), datosSucursal(['codigo' => 'SUC-100']))
        ->assertSessionHasErrors('codigo');
});

it('allows an admin to update a sucursal', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('sucursales.update', $sucursal), datosSucursal(['nombre' => 'Sucursal Editada']))
        ->assertRedirect(route('sucursales.index'));

    expect($sucursal->fresh()->nombre)->toBe('Sucursal Editada');
});

it('keeps its own codigo when updating', function (): void {
    $sucursal = Sucursal::factory()->create(['codigo' => 'SUC-100']);

    actingAs(actorConRol('admin'))
        ->put(route('sucursales.update', $sucursal), datosSucursal(['codigo' => 'SUC-100', 'nombre' => 'Nuevo']))
        ->assertSessionHasNoErrors();

    expect($sucursal->fresh()->nombre)->toBe('Nuevo');
});

it('deletes an empty sucursal', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('sucursales.destroy', $sucursal))
        ->assertRedirect(route('sucursales.index'));

    $this->assertDatabaseMissing('sucursales', ['id' => $sucursal->id]);
});

it('refuses to delete a sucursal with vehicles', function (): void {
    $sucursal = Sucursal::factory()->create();
    Vehiculo::factory()->for($sucursal)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('sucursales.destroy', $sucursal))
        ->assertRedirect();

    $this->assertDatabaseHas('sucursales', ['id' => $sucursal->id]);
});

it('refuses to delete a sucursal with conductores', function (): void {
    $sucursal = Sucursal::factory()->create();
    Conductor::factory()->for($sucursal)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('sucursales.destroy', $sucursal));

    $this->assertDatabaseHas('sucursales', ['id' => $sucursal->id]);
});

it('forbids a viewer from deleting a sucursal', function (): void {
    $sucursal = Sucursal::factory()->create();

    actingAs(actorConRol('visor'))
        ->delete(route('sucursales.destroy', $sucursal))
        ->assertForbidden();

    $this->assertDatabaseHas('sucursales', ['id' => $sucursal->id]);
});
