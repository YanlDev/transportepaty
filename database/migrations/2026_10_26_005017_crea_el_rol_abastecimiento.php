<?php

use App\Enums\Permiso;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * El rol del área de abastecimiento. Se crea con los permisos que ya
     * existen y le tocan según `Permiso::rolesPorDefecto()` (el tablero, las
     * unidades, la programación); los de su módulo los reparte el
     * sincronizador al crearlos, que corre después de las migraciones.
     *
     * Nadie cambia de rol acá: hoy abastecimiento entra como visor, y se
     * pasa al rol nuevo desde el panel.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existentes = Permission::query()->where('guard_name', 'web')->pluck('name')->all();

        $permisos = array_values(array_filter(
            Permiso::cases(),
            fn (Permiso $permiso): bool => in_array('abastecimiento', $permiso->rolesPorDefecto(), true)
                && in_array($permiso->value, $existentes, true),
        ));

        Role::findOrCreate('abastecimiento', 'web')->syncPermissions(
            array_map(fn (Permiso $permiso): string => $permiso->value, $permisos),
        );
    }

    public function down(): void
    {
        Role::query()->where('name', 'abastecimiento')->delete();
    }
};
