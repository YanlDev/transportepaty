<?php

use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles se ordenan por área de trabajo del circuito del ticket
     * (`RolBase`): cada rol queda con lo que le corresponde, borrar queda
     * solo para el administrador y liquidar los tickets pasa a
     * abastecimiento y contabilidad.
     *
     * Dania y Amneath dejaron la empresa: se dan de baja (no se borran, su
     * nombre sigue en el historial). Con eso el rol «visor», que era un rol
     * de espera, se queda sin nadie y se quita.
     */
    public function up(): void
    {
        app(SincronizadorPermisos::class)->aplicarRolesBase();

        $bajas = DB::table('users')->whereIn('username', ['dania', 'amneath2026'])->whereNull('baja_at')->pluck('id');

        DB::table('users')->whereIn('id', $bajas)->update([
            'baja_at' => now(),
            'motivo_baja' => 'Dejó la empresa.',
        ]);
        DB::table('model_has_roles')->whereIn('model_id', $bajas)->where('model_type', 'like', '%User')->delete();
        DB::table('model_has_permissions')->whereIn('model_id', $bajas)->where('model_type', 'like', '%User')->delete();

        $visor = DB::table('roles')->where('name', 'visor')->value('id');

        if ($visor !== null && ! DB::table('model_has_roles')->where('role_id', $visor)->exists()) {
            DB::table('role_has_permissions')->where('role_id', $visor)->delete();
            DB::table('roles')->where('id', $visor)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los roles se ajustan desde el panel.
    }
};
