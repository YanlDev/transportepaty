<?php

use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Pasa de roles fijos en el código a permisos configurables.
     *
     * Los roles base se crean antes de sincronizar para que cada uno reciba los
     * permisos equivalentes a lo que las policies le dejaban hacer hasta hoy:
     * nadie gana ni pierde acceso con este cambio.
     */
    public function up(): void
    {
        foreach (['admin', 'visor', 'contador'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }

        app(SincronizadorPermisos::class)->sincronizar();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')->delete();
    }
};
