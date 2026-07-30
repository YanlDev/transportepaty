<?php

use App\Models\Asignacion;
use App\Models\Conductor;
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
    return array_merge([
        'nombres' => 'Juan Carlos',
        'apellidos' => 'García Pérez',
        'documento' => '12345678',
        'licencia' => 'Q12345678',
        'categoria_licencia' => 'A-IIIa',
        'licencia_vence' => now()->addYear()->format('Y-m-d'),
        'telefono' => '999888777',
        'email' => 'juan@ejemplo.com',
        'procedencia' => 'Puno',
        'activo' => true,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('conductores.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('conductores.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conductores/index')
            ->has('conductores.data', 1)
            ->where('conductores.data.0.nombre_completo', $conductor->nombre_completo)
        );
});

it('filters the list by licencia', function (): void {
    Conductor::factory()->create(['licencia' => 'Q11111111']);
    Conductor::factory()->create(['licencia' => 'Q22222222']);

    actingAs(actorConRol('admin'))
        ->get(route('conductores.index', ['buscar' => 'Q1111']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('conductores.data', 1));
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
        ]))
        ->assertSessionHasErrors(['nombres', 'apellidos', 'documento']);
});

it('rejects a duplicate documento', function (): void {
    Conductor::factory()->create(['documento' => '12345678']);

    actingAs(actorConRol('admin'))
        ->post(route('conductores.store'), datosConductor(['documento' => '12345678']))
        ->assertSessionHasErrors('documento');
});

it('allows an admin to update a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'nombres' => 'Carlos Editado',
        ]))
        ->assertRedirect(route('conductores.index'));

    expect($conductor->fresh()->nombres)->toBe('Carlos Editado');
});

it('keeps its own documento when updating', function (): void {
    $conductor = Conductor::factory()->create(['documento' => '87654321']);

    actingAs(actorConRol('admin'))
        ->put(route('conductores.update', $conductor), datosConductor([
            'documento' => '87654321',
            'nombres' => 'Nuevo Nombre',
        ]))
        ->assertSessionHasNoErrors();

    expect($conductor->fresh()->nombres)->toBe('Nuevo Nombre');
});

it('deletes a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertRedirect(route('conductores.index'));

    $this->assertDatabaseMissing('conductores', ['id' => $conductor->id]);
});

it('forbids a viewer from deleting a conductor', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertForbidden();

    $this->assertDatabaseHas('conductores', ['id' => $conductor->id]);
});

it('refuses to delete a conductor with assignment history', function (): void {
    $asignacion = Asignacion::factory()->finalizada()->create();

    actingAs(actorConRol('admin'))
        ->from(route('conductores.index'))
        ->delete(route('conductores.destroy', $asignacion->conductor))
        ->assertRedirect(route('conductores.index'));

    expect(Conductor::find($asignacion->conductor_id))->not->toBeNull()
        ->and(Asignacion::count())->toBe(1);
});

it('deletes a conductor that never had an assignment', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('conductores.destroy', $conductor))
        ->assertRedirect(route('conductores.index'));

    expect(Conductor::find($conductor->id))->toBeNull();
});
