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
     * El rol del almacén, con todo lo que le toca según `RolBase`: los
     * permisos nuevos del módulo y los que ya existían (el tablero, las
     * unidades, el mantenimiento). Solo se arma este rol: los demás reciben
     * lo suyo del almacén al sincronizar, sin tocar lo ajustado en el panel.
     *
     * Nadie cambia de rol acá: se asigna desde el panel.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        Role::findOrCreate(RolBase::Almacen->value, 'web')->syncPermissions(
            array_map(fn (Permiso $permiso): string => $permiso->value, RolBase::Almacen->permisos()),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los roles se ajustan desde el panel.
    }
};
