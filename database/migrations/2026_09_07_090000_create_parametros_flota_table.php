<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_flota', function (Blueprint $table) {
            $table->id();
            // El tamaño de la flota y los días disponibles son el divisor de
            // todo costo fijo: van juntos y en un solo lugar para que cambiar
            // la flota recalcule la estructura entera de una vez.
            $table->unsignedInteger('tamano_flota');
            $table->decimal('dias_ano', 6, 2);
            $table->decimal('dias_mantenimiento', 6, 2);
            $table->decimal('dias_certificaciones', 6, 2);
            $table->decimal('dias_sincronizacion', 6, 2);
            $table->decimal('igv_pct', 5, 4);
            $table->decimal('margen_pct_default', 5, 4);
            $table->decimal('viatico_dia', 10, 2);
            $table->timestamps();
        });

        // Los valores del Excel «ESTRUCTURA DE COSTOS - SERVICIOS A MINSUR»
        // (agosto 2026). Fila única: no hay alta de parámetros, solo edición.
        DB::table('parametros_flota')->insert([
            'tamano_flota' => 100,
            'dias_ano' => 365,
            // Un camión no factura 365 días: pierde días en mantenimiento
            // programado, en revisiones técnicas y esperando la sincronización
            // de retornos. Quedan 303.13 días vendibles al año.
            'dias_mantenimiento' => 4.87,
            'dias_certificaciones' => 4,
            'dias_sincronizacion' => 53,
            'igv_pct' => 0.18,
            'margen_pct_default' => 0.12,
            'viatico_dia' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_flota');
    }
};
