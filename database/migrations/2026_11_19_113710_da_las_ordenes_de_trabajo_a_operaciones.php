<?php

use App\Enums\Permiso;
use App\Enums\RolBase;
use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Operaciones lleva el ciclo de la orden de trabajo: crea la pre-orden,
     * aprueba el requerimiento del mecánico y valida el trabajo terminado.
     * Para eso necesita ver el mantenimiento y gestionar las órdenes. Solo
     * se le suma eso; lo que se haya ajustado desde el panel no se toca.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        Role::findOrCreate(RolBase::Operaciones->value, 'web')->givePermissionTo([
            Permiso::MantenimientoVer->value,
            Permiso::MantenimientoGestionar->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los roles se ajustan desde el panel.
    }
};
