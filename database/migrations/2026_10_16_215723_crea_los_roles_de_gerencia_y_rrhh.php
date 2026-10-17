<?php

use App\Enums\Permiso;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles por área que faltaban:
     *
     * - `gerencia`: ve todo y no modifica nada.
     * - `rrhh`: conductores (con sus documentos) y asistencia.
     *
     * Se crean con los permisos que hoy les tocan según
     * `Permiso::rolesPorDefecto()`; lo que se agregue después lo reparte el
     * sincronizador. Nadie cambia de rol acá: eso se hace desde el panel.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['gerencia', 'rrhh'] as $nombre) {
            $permisos = array_values(array_filter(
                Permiso::cases(),
                fn (Permiso $permiso): bool => in_array($nombre, $permiso->rolesPorDefecto(), true),
            ));

            Role::findOrCreate($nombre, 'web')->syncPermissions(
                array_map(fn (Permiso $permiso): string => $permiso->value, $permisos),
            );
        }
    }

    public function down(): void
    {
        Role::query()->whereIn('name', ['gerencia', 'rrhh'])->delete();
    }
};
