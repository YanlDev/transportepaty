<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuando el mecánico no puede anotar desde su celular, quien gestiona la
     * orden lo hace por él: el paso queda a nombre del mecánico que hizo el
     * trabajo y acá queda quién lo anotó. Vacío = lo anotó el mismo mecánico.
     */
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->foreignId('requerimiento_registrado_por')->nullable()->after('requerimiento_por')->constrained('users')->nullOnDelete();
            $table->foreignId('inicio_registrado_por')->nullable()->after('iniciado_por')->constrained('users')->nullOnDelete();
            $table->foreignId('termino_registrado_por')->nullable()->after('terminado_por')->constrained('users')->nullOnDelete();
        });

        Schema::table('tareas_orden', function (Blueprint $table) {
            $table->foreignId('registrada_por')->nullable()->after('hecha_por')->constrained('users')->nullOnDelete();
        });

        Schema::table('repuestos_orden', function (Blueprint $table) {
            $table->foreignId('registrado_por')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repuestos_orden', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_por');
        });

        Schema::table('tareas_orden', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrada_por');
        });

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requerimiento_registrado_por');
            $table->dropConstrainedForeignId('inicio_registrado_por');
            $table->dropConstrainedForeignId('termino_registrado_por');
        });
    }
};
