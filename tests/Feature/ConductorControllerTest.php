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
function datosConductor(array $overrides = []): array
{
    $sucursal = Sucursal::factory()->create();

    return array_merge([
        'sucursal_id' => $sucursal->id,
        'nombres' => 'Juan Carlos',
        'apellidos' => 'García Pérez',
        'documento' => '12345678',
        'licencia' => 'Q12345678',
        'categoria_licencia' => 'A-IIa',
        'licencia_vence' => now()->addYear()->format('Y-m-d'),
        'telefono' => '+51 999 888 777',
        'email' => 'juan@ejemplo.com',
        'activo' => true,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('conductores.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list with counts', function (): void {
    $conductor = Conductor::factory()->create();
    Vehiculo::factory()->count(2)->for($conductor)->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conductores/index')
            ->has('conductores.data', 1)
            ->where('conductores.data.0.vehiculos_count', 2)
            ->where('conductores.data.0.nombre_completo', $conductor->nombre_completo)
        );
});

it('exposes the edit form with a date-only license expiry', function (): void {
    $conductor = Conductor::factory()->create([
        'licencia_vence' => '2030-05-15',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.edit', $conductor))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conductores/edit')
            ->where('conductor.nombre_completo', $conductor->nombre_completo)
            ->where('conductor.licencia_vence', '2030-05-15')
        );
});

it('forbids drivers from the list', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('conductores.index'))
        ->assertForbidden();
});

it('allows an admin to create a conductor', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor())
        ->assertRedirect(route('conductores.index'));

    $this->assertDatabaseHas('conductores', ['documento' => '12345678']);
});

it('forbids a viewer from creating a conductor', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('conductores.store'), datosConductor())
        ->assertForbidden();

    $this->assertDatabaseCount('conductores', 0);
});

it('validates required fields when creating', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor([
            'nombres' => '',
            'apellidos' => '',
            'documento' => '',
            'sucursal_id' => '',
        ]))
        ->assertSessionHasErrors(['nombres', 'apellidos', 'documento', 'sucursal_id']);
});

it('rejects a duplicate documento', function (): void {
    $sucursal = Sucursal::factory()->create();
    Conductor::factory()->for($sucursal)->create(['documento' => '12345678']);

    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor(['documento' => '12345678']))
        ->assertSessionHasErrors('documento');
});

it('allows an admin to update a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'sucursal_id' => $conductor->sucursal_id,
            'nombres' => 'Carlos Editado',
        ]))
        ->assertRedirect(route('conductores.index'));

    expect($conductor->fresh()->nombres)->toBe('Carlos Editado');
});

it('keeps its own documento when updating', function (): void {
    $conductor = Conductor::factory()->create(['documento' => '87654321']);

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'sucursal_id' => $conductor->sucursal_id,
            'documento' => '87654321',
            'nombres' => 'Nuevo Nombre',
        ]))
        ->assertSessionHasNoErrors();

    expect($conductor->fresh()->nombres)->toBe('Nuevo Nombre');
});

it('deletes a conductor without vehicles', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertRedirect(route('conductores.index'));

    $this->assertDatabaseMissing('conductores', ['id' => $conductor->id]);
});

it('refuses to delete a conductor with vehicles', function (): void {
    $conductor = Conductor::factory()->create();
    Vehiculo::factory()->for($conductor)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertRedirect();

    $this->assertDatabaseHas('conductores', ['id' => $conductor->id]);
});

it('forbids a viewer from deleting a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertForbidden();

    $this->assertDatabaseHas('conductores', ['id' => $conductor->id]);
});
