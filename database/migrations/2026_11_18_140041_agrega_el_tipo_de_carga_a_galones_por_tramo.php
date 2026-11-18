<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un tramo puede gastar distinto según lo que lleva: Lima → Mina con
 * particular tiene su propia tabla, aparte de la de Ransa. Sin carga, la
 * fila vale para cualquiera (como hasta ahora); con carga, manda sobre la
 * general solo cuando el tramo va cargado con eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galones_por_tramo', function (Blueprint $table): void {
            $table->string('tipo_carga', 20)->nullable()->after('zona_destino');
            $table->dropUnique(['zona_origen', 'zona_destino']);
            $table->unique(['zona_origen', 'zona_destino', 'tipo_carga']);
        });
    }

    public function down(): void
    {
        Schema::table('galones_por_tramo', function (Blueprint $table): void {
            $table->dropUnique(['zona_origen', 'zona_destino', 'tipo_carga']);
        });

        DB::table('galones_por_tramo')->whereNotNull('tipo_carga')->delete();

        Schema::table('galones_por_tramo', function (Blueprint $table): void {
            $table->dropColumn('tipo_carga');
            $table->unique(['zona_origen', 'zona_destino']);
        });
    }
};
