<?php

namespace App\Services;

use App\Enums\Permiso;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Alinea la tabla de permisos con el catálogo de `Permiso`.
 *
 * - Crea los permisos nuevos y se los da a los roles base que indica
 *   `rolesPorDefecto()`, solo esa primera vez.
 * - Borra los que ya no están en el catálogo, que no protegerían nada.
 * - Le deja al admin todos: es el rol que no se puede quedar sin acceso, y por
 *   eso el panel no deja editarlo.
 *
 * Es idempotente: corre en cada deploy sin pisar lo que se configuró a mano.
 */
class SincronizadorPermisos
{
    public const ROL_ADMIN = 'admin';

    private const GUARD = 'web';

    public function sincronizar(): void
    {
        $existentes = Permission::query()
            ->where('guard_name', self::GUARD)
            ->pluck('name')
            ->all();

        $nuevos = array_filter(
            Permiso::cases(),
            fn (Permiso $permiso): bool => ! in_array($permiso->value, $existentes, true),
        );

        foreach ($nuevos as $permiso) {
            Permission::query()->create(['name' => $permiso->value, 'guard_name' => self::GUARD]);
        }

        Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', Permiso::valores())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(self::ROL_ADMIN, self::GUARD)
            ->load('permissions')
            ->syncPermissions(Permiso::valores());

        $porRol = [];

        foreach ($nuevos as $permiso) {
            foreach ($permiso->rolesPorDefecto() as $nombreRol) {
                $porRol[$nombreRol][] = $permiso->value;
            }
        }

        Role::query()
            ->with('permissions')
            ->where('guard_name', self::GUARD)
            ->whereIn('name', array_keys($porRol))
            ->get()
            ->each(fn (Role $rol) => $rol->givePermissionTo($porRol[$rol->name]));
    }
}
