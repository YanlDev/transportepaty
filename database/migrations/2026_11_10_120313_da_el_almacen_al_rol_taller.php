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
     * Taller y almacén van juntos: quien da el OK de salida también despacha,
     * recibe compras, cuenta y mantiene el catálogo. Al rol Taller solo se le
     * suma eso; lo que se le haya ajustado desde el panel no se toca, y el
     * rol Almacén sigue existiendo para quien solo atienda el mostrador.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();

        Role::findOrCreate(RolBase::Taller->value, 'web')->givePermissionTo([
            Permiso::AlmacenCatalogo->value,
            Permiso::AlmacenIngresar->value,
            Permiso::AlmacenDespachar->value,
            Permiso::AlmacenInventariar->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los roles se ajustan desde el panel.
    }
};
