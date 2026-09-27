<?php

use App\Enums\Permiso;
use App\Models\User;
use App\Models\Viaje;
use App\Services\SincronizadorPermisos;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('keeps what each base role could do before permissions existed', function (): void {
    $permisosDe = fn (string $rol): array => Role::findByName($rol)->permissions->pluck('name')->sort()->values()->all();

    expect($permisosDe('admin'))->toEqualCanonicalizing(Permiso::valores())
        ->and($permisosDe('visor'))->toEqualCanonicalizing([
            'tablero.ver', 'vehiculos.ver', 'conductores.ver', 'viajes.ver',
            'programacion.ver', 'clientes.ver', 'cotizaciones.ver',
        ])
        ->and($permisosDe('contador'))->toEqualCanonicalizing([
            'tablero.ver', 'vehiculos.ver', 'viajes.ver',
            'cobranza.ver', 'cobranza.gestionar', 'cuentas.ver', 'cuentas.gestionar',
        ]);
});

it('does not restore a permission removed by hand when syncing again', function (): void {
    Role::findByName('visor')->revokePermissionTo('viajes.ver');
    Permission::create(['name' => 'obsoleto.ver', 'guard_name' => 'web']);

    app(SincronizadorPermisos::class)->sincronizar();

    expect(Role::findByName('visor')->hasPermissionTo('viajes.ver'))->toBeFalse()
        ->and(Permission::where('name', 'obsoleto.ver')->exists())->toBeFalse();
});

it('shows the roles with their permissions and the catalog grouped by module', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('roles.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->where('roles.0.name', 'admin')
            ->where('roles.0.bloqueado', true)
            ->has('modulos', count(Permiso::MODULOS))
            ->where('modulos.0.clave', 'tablero')
        );
});

it('keeps the roles panel for admins only', function (string $rol): void {
    actingAs(actorConRol($rol))->get(route('roles.index'))->assertForbidden();
    actingAs(actorConRol($rol))->post(route('roles.store'), ['name' => 'intruso'])->assertForbidden();
})->with(['visor', 'contador']);

it('creates a role copying the permissions of another', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('roles.store'), [
            'name' => '  Despachador ',
            'copiar_de' => Role::findByName('visor')->id,
        ])
        ->assertRedirect();

    $rol = Role::findByName('despachador');

    expect($rol->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(Role::findByName('visor')->permissions->pluck('name')->all());
});

it('rejects a duplicated role name or an unknown permission', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('roles.store'), ['name' => 'visor'])
        ->assertSessionHasErrors('name');

    actingAs(actorConRol('admin'))
        ->post(route('roles.store'), ['name' => 'nuevo', 'permisos' => ['viajes.volar']])
        ->assertSessionHasErrors('permisos.0');
});

it('saves the permissions checked for a role', function (): void {
    $rol = Role::create(['name' => 'despachador', 'guard_name' => 'web']);

    actingAs(actorConRol('admin'))
        ->put(route('roles.update', $rol), [
            'name' => 'despachador',
            'permisos' => ['programacion.ver', 'programacion.crear', 'programacion.avisar'],
        ])
        ->assertRedirect(route('roles.index', ['rol' => $rol->id]));

    expect($rol->fresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['programacion.ver', 'programacion.crear', 'programacion.avisar']);
});

it('never lets the admin role be edited or deleted', function (): void {
    $admin = Role::findByName('admin');

    actingAs(actorConRol('admin'))
        ->put(route('roles.update', $admin), ['name' => 'admin', 'permisos' => []]);
    actingAs(actorConRol('admin'))->delete(route('roles.destroy', $admin));

    expect(Role::findByName('admin')->permissions)->toHaveCount(count(Permiso::cases()));
});

it('does not delete a role that still has users', function (): void {
    actingAs(actorConRol('admin'))->delete(route('roles.destroy', Role::findByName('contador')));

    expect(Role::where('name', 'contador')->exists())->toBeFalse();

    actorConRol('visor');
    actingAs(actorConRol('admin'))->delete(route('roles.destroy', Role::findByName('visor')));

    expect(Role::where('name', 'visor')->exists())->toBeTrue();
});

it('lets a custom role do exactly what was checked for it', function (): void {
    Role::create(['name' => 'anulador', 'guard_name' => 'web'])
        ->givePermissionTo(['viajes.ver', 'viajes.anular']);
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('anulador'))
        ->post(route('viajes.anular', $viaje), ['motivo' => 'Reemitida'])
        ->assertRedirect();

    actingAs(actorConRol('anulador'))
        ->delete(route('viajes.destroy', $viaje))
        ->assertForbidden();
});

it('adds per-user permissions on top of the role', function (): void {
    $usuario = actorConRol('visor');

    actingAs(actorConRol('admin'))
        ->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name,
            'username' => $usuario->username,
            'role' => 'visor',
            'permisos' => ['viajes.anular'],
        ])
        ->assertRedirect(route('usuarios.index'));

    $usuario = User::find($usuario->id);

    expect($usuario->getDirectPermissions()->pluck('name')->all())->toBe(['viajes.anular'])
        ->and($usuario->can('anular', Viaje::factory()->create()))->toBeTrue()
        ->and(actorConRol('visor')->can('anular', Viaje::factory()->create()))->toBeFalse();
});

it('shares the session permissions with the frontend', function (): void {
    $usuario = actorConRol('contador')->givePermissionTo('clientes.ver');

    actingAs($usuario)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permisos', fn ($permisos): bool => collect($permisos)->contains('clientes.ver')
                && collect($permisos)->contains('cobranza.ver')
                && ! collect($permisos)->contains('viajes.anular'))
        );
});
