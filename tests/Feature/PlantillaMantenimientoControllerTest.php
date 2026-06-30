<?php

use App\Models\PlantillaMantenimiento;
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
function datosPlantilla(array $overrides = []): array
{
    return array_merge([
        'nombre' => 'Cambio de aceite + filtro',
        'tipo_mantenimiento' => 'aceite',
        'marca' => 'Toyota',
        'modelo' => 'Hilux',
        'tipo_vehiculo' => null,
        'intervalo_km' => 5000,
        'intervalo_meses' => 6,
        'descripcion' => null,
        'costo_estimado' => 150,
        'orden' => 1,
        'activo' => true,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('mantenedor.plantillas.index'))->assertRedirect(route('login'));
});

it('lets an admin see the templates list', function (): void {
    PlantillaMantenimiento::factory()->count(3)->create();

    actingAs(actorConRol('admin'))
        ->get(route('mantenedor.plantillas.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenedor/plantillas-mantenimiento')
            ->has('plantillas.data', 3)
            ->has('tiposVehiculo')
        );
});

it('forbids a viewer and a driver from the templates list', function (): void {
    actingAs(actorConRol('visor'))->get(route('mantenedor.plantillas.index'))->assertForbidden();
    actingAs(actorConRol('conductor'))->get(route('mantenedor.plantillas.index'))->assertForbidden();
});

it('lets an admin create a template', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('mantenedor.plantillas.store'), datosPlantilla())
        ->assertRedirect(route('mantenedor.plantillas.index'));

    $this->assertDatabaseHas('plantillas_mantenimiento', [
        'nombre' => 'Cambio de aceite + filtro',
        'marca' => 'Toyota',
        'intervalo_km' => 5000,
    ]);
});

it('forbids a viewer from creating a template', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('mantenedor.plantillas.store'), datosPlantilla())
        ->assertForbidden();

    $this->assertDatabaseCount('plantillas_mantenimiento', 0);
});

it('requires at least one interval', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('mantenedor.plantillas.store'), datosPlantilla([
            'intervalo_km' => null,
            'intervalo_meses' => null,
        ]))
        ->assertSessionHasErrors('intervalo_km');
});

it('validates required fields', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('mantenedor.plantillas.store'), datosPlantilla([
            'nombre' => '',
            'tipo_mantenimiento' => '',
        ]))
        ->assertSessionHasErrors(['nombre', 'tipo_mantenimiento']);
});

it('lets an admin update a template', function (): void {
    $plantilla = PlantillaMantenimiento::factory()->create(['intervalo_km' => 5000]);

    actingAs(actorConRol('admin'))
        ->put(route('mantenedor.plantillas.update', $plantilla), datosPlantilla(['intervalo_km' => 8000]))
        ->assertRedirect(route('mantenedor.plantillas.index'));

    expect($plantilla->fresh()->intervalo_km)->toBe(8000);
});

it('lets an admin delete a template', function (): void {
    $plantilla = PlantillaMantenimiento::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('mantenedor.plantillas.destroy', $plantilla))
        ->assertRedirect(route('mantenedor.plantillas.index'));

    $this->assertDatabaseMissing('plantillas_mantenimiento', ['id' => $plantilla->id]);
});

it('forbids a driver from deleting a template', function (): void {
    $plantilla = PlantillaMantenimiento::factory()->create();

    actingAs(actorConRol('conductor'))
        ->delete(route('mantenedor.plantillas.destroy', $plantilla))
        ->assertForbidden();

    $this->assertDatabaseHas('plantillas_mantenimiento', ['id' => $plantilla->id]);
});
