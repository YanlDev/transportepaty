<?php

use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * El rol Mecánico y los dos permisos nuevos (pedir un trabajo y ejecutar
     * las órdenes asignadas). El sincronizador crea el rol base y reparte
     * cada permiso nuevo a quien le toca según `RolBase`; lo que se haya
     * ajustado desde el panel no se toca.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->sincronizar();
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los roles se ajustan desde el panel.
    }
};
