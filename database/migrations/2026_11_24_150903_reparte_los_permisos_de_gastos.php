<?php

use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos de los vales de gasto (`gastos.*`) llegan a los roles
     * base según `RolBase`: todas las áreas piden, gerencia aprueba y lleva
     * las categorías. No toca lo que ya se ajustó desde el panel.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los roles se ajustan desde el panel.
    }
};
