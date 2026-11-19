<?php

use App\Enums\Permiso;
use App\Enums\RolBase;
use App\Services\ArticulosDelPlan;
use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Cada ítem de los planes preventivos pasa a tener su artículo del
     * almacén (en stock cero) y su unidad queda igual a la del artículo. Y
     * operaciones puede ajustar los planes: renombrar, cambiar o quitar ítems.
     */
    public function up(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            // De qué plan nació el artículo: mientras no se mueva, sigue a su ítem.
            $table->foreignId('plan_mantenimiento_id')->nullable()->constrained('planes_mantenimiento')->nullOnDelete();
        });

        app(SincronizadorPermisos::class)->sincronizar();
        Role::findOrCreate(RolBase::Operaciones->value, 'web')->givePermissionTo(Permiso::MantenimientoConfigurar->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // En las pruebas cada una arma lo que necesita: no se llenan de
        // artículos todas las bases de prueba.
        if (! app()->runningUnitTests()) {
            app(ArticulosDelPlan::class)->asegurar();
        }
    }

    public function down(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_mantenimiento_id');
        });
    }
};
