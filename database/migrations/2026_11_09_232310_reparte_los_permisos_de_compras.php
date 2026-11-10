<?php

use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos de compras (`compras.*`) y el de anular órdenes de
     * trabajo llegan a los roles base que les toca según `RolBase`: gerencia
     * aprueba, operaciones aprueba las emergencias, taller y almacén piden.
     * No toca lo que ya se ajustó desde el panel.
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
