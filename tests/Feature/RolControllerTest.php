<?php

use App\Enums\Permiso;
use App\Enums\RolBase;
use App\Models\User;
use App\Models\Viaje;
use App\Services\SincronizadorPermisos;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('leaves every base role exactly as the catalog of roles says', function (): void {
    $permisosDe = fn (string $rol): array => Role::findByName($rol)->permissions->pluck('name')->sort()->values()->all();

    foreach (RolBase::cases() as $rol) {
        expect($permisosDe($rol->value))->toEqualCanonicalizing(array_map(fn (Permiso $permiso): string => $permiso->value, $rol->permisos()));
    }

    // Ya no hay rol de espera.
    expect(Role::query()->where('name', 'visor')->exists())->toBeFalse();
});

it('leaves deleting to the admin and settling tickets to fuel and accounting', function (): void {
    $eliminar = array_filter(Permiso::cases(), fn (Permiso $permiso): bool => str_ends_with($permiso->value, '.eliminar') && $permiso !== Permiso::ProgramacionEliminar);

    foreach (RolBase::cases() as $rol) {
        if ($rol === RolBase::Admin) {
            continue;
        }

        foreach ($eliminar as $permiso) {
            expect($rol->tiene($permiso))->toBeFalse("{$rol->value} no debería tener {$permiso->value}");
        }
    }

    expect(Permiso::ProgramacionCerrar->rolesPorDefecto())->toEqualCanonicalizing(['abastecimiento', 'contador'])
        ->and(Permiso::AbastecimientoExcederTope->rolesPorDefecto())->toBe(['gerencia'])
        ->and(Permiso::ProgramacionAsignarGr->rolesPorDefecto())->toBe(['operaciones']);
});

it('does not restore a permission removed by hand when syncing again', function (): void {
    Role::findByName('contador')->revokePermissionTo('viajes.ver');
    Permission::create(['name' => 'obsoleto.ver', 'guard_name' => 'web']);

    app(SincronizadorPermisos::class)->sincronizar();

    expect(Role::findByName('contador')->hasPermissionTo('viajes.ver'))->toBeFalse()
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
            // Cada módulo dice su área, para que el panel se agrupe como el menú.
            ->where('modulos.1.clave', 'programacion')
            ->where('modulos.1.area', 'Operación')
            ->where('modulos.2.clave', 'taller')
            ->where('modulos.2.area', 'Operación')
            ->where('modulos.5.clave', 'vehiculos')
            ->where('modulos.5.area', 'Flota')
            ->where('modulos.8.clave', 'conductores')
            ->where('modulos.8.area', 'Flota')
        );
});

/**
 * Gerencia ve todo y solo aprueba las excepciones; RRHH maneja conductores y
 * asistencia, y entra por el tablero como todos.
 */
it('creates the gerencia and rrhh roles by area', function (): void {
    $permisosDe = fn (string $rol): array => Role::findByName($rol, 'web')->permissions->pluck('name')->sort()->values()->all();

    $todosLosVer = collect(Permiso::valores())->filter(fn (string $permiso): bool => str_ends_with($permiso, '.ver'))->push('abastecimiento.exceder_tope')->sort()->values()->all();

    expect($permisosDe('gerencia'))->toBe($todosLosVer)
        ->and($permisosDe('rrhh'))->toBe(collect([
            'tablero.ver',
            'conductores.ver', 'conductores.crear', 'conductores.editar',
            'asistencia.ver', 'asistencia.marcar', 'asistencia.ajustar',
        ])->sort()->values()->all());
});

it('gives every module an area', function (): void {
    foreach (array_keys(Permiso::MODULOS) as $modulo) {
        expect(Permiso::areaDeModulo($modulo))->not->toBe('Otros');
    }
});

it('keeps the roles panel for admins only', function (string $rol): void {
    actingAs(actorConRol($rol))->get(route('roles.index'))->assertForbidden();
    actingAs(actorConRol($rol))->post(route('roles.store'), ['name' => 'intruso'])->assertForbidden();
})->with(['visor', 'contador']);

it('creates a role copying the permissions of another', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('roles.store'), [
            'name' => '  Despachador ',
            'copiar_de' => Role::findByName('taller')->id,
        ])
        ->assertRedirect();

    $rol = Role::findByName('despachador');

    expect($rol->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(Role::findByName('taller')->permissions->pluck('name')->all());
});

it('rejects a duplicated role name or an unknown permission', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('roles.store'), ['name' => 'taller'])
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

    actorConRol('taller');
    actingAs(actorConRol('admin'))->delete(route('roles.destroy', Role::findByName('taller')));

    expect(Role::where('name', 'taller')->exists())->toBeTrue();
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
            // Fijos: los que sortea la factory no siempre pasan la validación
            // del formulario.
            'name' => 'Visor Con Extra',
            'username' => 'visor_extra',
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

it('moves the system screens under settings and keeps the old urls working', function (): void {
    expect(route('usuarios.index', absolute: false))->toBe('/settings/usuarios')
        ->and(route('roles.index', absolute: false))->toBe('/settings/roles')
        ->and(route('whatsapp.index', absolute: false))->toBe('/settings/whatsapp');

    actingAs(actorConRol('admin'))->get('/usuarios')->assertRedirect('/settings/usuarios');
    actingAs(actorConRol('admin'))->get('/roles')->assertRedirect('/settings/roles');
    actingAs(actorConRol('admin'))->get('/whatsapp')->assertRedirect('/settings/whatsapp');
});

it('says who has each role', function (): void {
    $dania = User::factory()->create(['name' => 'Dania', 'username' => 'dania'])->assignRole('abastecimiento');

    actingAs(actorConRol('admin'))
        ->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page->where('roles', function ($roles) use ($dania): bool {
            $abastecimiento = collect($roles)->firstWhere('name', 'abastecimiento');

            return $abastecimiento['miembros'] === [['id' => $dania->id, 'nombre' => 'Dania', 'usuario' => 'dania']];
        }));
});
