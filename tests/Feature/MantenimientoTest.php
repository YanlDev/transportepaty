<?php

use App\Models\Conductor;
use App\Models\Mantenimiento;
use App\Models\PlantillaMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
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
 * @return array{0: User, 1: Vehiculo}
 */
function conductorAsignado(): array
{
    $user = actorConRol('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);
    $vehiculo = Vehiculo::factory()->paraConductor($conductor)->create();

    return [$user, $vehiculo];
}

it('redirects guests to login', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $this->get(route('vehiculos.mantenimiento.index', $vehiculo))
        ->assertRedirect(route('login'));
});

it('shows the maintenance page with records and plan to an admin', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    Mantenimiento::factory()->for($vehiculo)->conItems(2)->count(2)->create();

    actingAs(actorConRol('admin'))
        ->get(route('vehiculos.mantenimiento.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/mantenimiento')
            ->has('mantenimientos', 2)
            ->has('plan')
            ->has('estadisticas')
            ->has('estado_general')
            ->has('odometro_minimo')
            ->where('puede.registrar', true)
            ->where('puede.gestionar', true)
        );
});

it('exposes the plan and summary in the shape the frontend expects', function (): void {
    $vehiculo = Vehiculo::factory()->create(['imei' => 'x', 'kilometraje' => 14800, 'marca' => 'Toyota', 'modelo' => 'Hilux']);
    $plantilla = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000, 'intervalo_meses' => null,
    ]);
    $m = Mantenimiento::factory()->for($vehiculo)->create(['odometro' => 10000, 'costo_total' => 150]);
    $m->items()->create(['plantilla_id' => $plantilla->id, 'nombre' => $plantilla->nombre, 'tipo_mantenimiento' => $plantilla->tipo_mantenimiento]);

    actingAs(actorConRol('admin'))
        ->get(route('vehiculos.mantenimiento.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.0.km_restantes', 200)
            ->where('plan.0.proximo_km', 15000)
            ->where('plan.0.vencido', false)
            ->where('plan.0.progreso', 96)
            ->has('estado_general.al_dia')
            ->has('estado_general.vencido')
            ->where('estadisticas.total_mantenimientos', 1)
            ->has('costos_anio.total')
            ->has('costos_anio.categorias')
        );
});

it('lets a visor view with read-only permissions', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('vehiculos.mantenimiento.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('puede.registrar', false)
            ->where('puede.gestionar', false)
        );
});

it('lets a driver view only their assigned vehicle', function (): void {
    [$user, $vehiculo] = conductorAsignado();

    actingAs($user)
        ->get(route('vehiculos.mantenimiento.index', $vehiculo))
        ->assertSuccessful();
});

it('forbids a driver from viewing another vehicle', function (): void {
    [$user] = conductorAsignado();
    $ajeno = Vehiculo::factory()->create();

    actingAs($user)
        ->get(route('vehiculos.mantenimiento.index', $ajeno))
        ->assertForbidden();
});

it('lets an admin register a maintenance with items', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.mantenimiento.store', $vehiculo), [
            'fecha_realizado' => '2026-06-01',
            'odometro' => 15000,
            'proveedor' => 'Taller Los Olivos',
            'factura_numero' => 'F001-123',
            'observaciones' => 'Cambio programado',
            'items' => [
                ['nombre' => 'Cambio de aceite', 'tipo_mantenimiento' => 'preventivo', 'costo' => 120],
                ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'preventivo', 'costo' => 45],
            ],
        ])
        ->assertRedirect();

    $mantenimiento = Mantenimiento::firstOrFail();

    expect($mantenimiento->vehiculo_id)->toBe($vehiculo->id)
        ->and((int) $mantenimiento->odometro)->toBe(15000)
        ->and($mantenimiento->proveedor)->toBe('Taller Los Olivos')
        ->and($mantenimiento->items)->toHaveCount(2)
        ->and((float) $mantenimiento->costo_total)->toBe(165.0);
});

it('allows backfilling a past maintenance with a lower odometer', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 54710]);
    Mantenimiento::factory()->for($vehiculo)->create(['odometro' => 50000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.mantenimiento.store', $vehiculo), [
            'fecha_realizado' => '2026-03-21',
            'odometro' => 40000,
            'items' => [
                ['nombre' => 'Cambio de aceite', 'tipo_mantenimiento' => 'aceite'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(
        Mantenimiento::where('vehiculo_id', $vehiculo->id)->where('odometro', 40000)->exists()
    )->toBeTrue();
});

it('links a registered item to its maintenance template', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000, 'marca' => 'Toyota', 'modelo' => 'Hilux']);
    $plantilla = PlantillaMantenimiento::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Hilux', 'nombre' => 'Cambio de aceite + filtro', 'tipo_mantenimiento' => 'aceite',
    ]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.mantenimiento.store', $vehiculo), [
            'fecha_realizado' => '2026-06-01',
            'odometro' => 15000,
            'items' => [
                ['plantilla_id' => $plantilla->id, 'nombre' => $plantilla->nombre, 'tipo_mantenimiento' => 'aceite', 'costo' => 120],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mantenimiento_items', [
        'plantilla_id' => $plantilla->id,
        'nombre' => 'Cambio de aceite + filtro',
    ]);
});

it('validates items are required', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.mantenimiento.store', $vehiculo), [
            'fecha_realizado' => '2026-06-01',
            'odometro' => 15000,
        ])
        ->assertSessionHasErrors(['items']);
});

it('validates item nombre is required', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000]);

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.mantenimiento.store', $vehiculo), [
            'fecha_realizado' => '2026-06-01',
            'odometro' => 15000,
            'items' => [
                ['tipo_mantenimiento' => 'preventivo'],
            ],
        ])
        ->assertSessionHasErrors(['items.0.nombre']);
});

it('forbids a driver from registering', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000]);

    actingAs(actorConRol('conductor'))
        ->post(route('vehiculos.mantenimiento.store', $vehiculo), [
            'fecha_realizado' => '2026-06-01',
            'odometro' => 15000,
            'items' => [['nombre' => 'Aceite', 'tipo_mantenimiento' => 'preventivo']],
        ])
        ->assertForbidden();
});

it('lets an admin update a maintenance', function (): void {
    $vehiculo = Vehiculo::factory()->create(['kilometraje' => 10000]);
    $mantenimiento = Mantenimiento::factory()->for($vehiculo)->conItems(1)->create([
        'odometro' => 15000,
        'proveedor' => 'Taller A',
    ]);

    actingAs(actorConRol('admin'))
        ->put(route('vehiculos.mantenimiento.update', [$vehiculo, $mantenimiento]), [
            'proveedor' => 'Taller B',
            'observaciones' => 'Actualizado',
        ])
        ->assertRedirect();

    $mantenimiento->refresh();

    expect($mantenimiento->proveedor)->toBe('Taller B')
        ->and($mantenimiento->observaciones)->toBe('Actualizado');
});

it('forbids a driver from updating', function (): void {
    [$user, $vehiculo] = conductorAsignado();
    $mantenimiento = Mantenimiento::factory()->for($vehiculo)->create();

    actingAs($user)
        ->put(route('vehiculos.mantenimiento.update', [$vehiculo, $mantenimiento]), [
            'proveedor' => 'Hacked',
        ])
        ->assertForbidden();
});

it('lets an admin delete a maintenance', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $mantenimiento = Mantenimiento::factory()->for($vehiculo)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('vehiculos.mantenimiento.destroy', [$vehiculo, $mantenimiento]))
        ->assertRedirect();

    expect(Mantenimiento::find($mantenimiento->id))->toBeNull();
});

it('forbids a driver from deleting', function (): void {
    [$user, $vehiculo] = conductorAsignado();
    $mantenimiento = Mantenimiento::factory()->for($vehiculo)->create();

    actingAs($user)
        ->delete(route('vehiculos.mantenimiento.destroy', [$vehiculo, $mantenimiento]))
        ->assertForbidden();
});

it('returns 404 when the maintenance does not belong to the vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();
    $mantenimiento = Mantenimiento::factory()->for($otro)->create();

    actingAs(actorConRol('admin'))
        ->delete(route('vehiculos.mantenimiento.destroy', [$vehiculo, $mantenimiento]))
        ->assertNotFound();
});
