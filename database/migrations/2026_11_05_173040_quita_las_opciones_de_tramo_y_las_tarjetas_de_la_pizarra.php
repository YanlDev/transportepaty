<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con todo pasado a las rutas, las opciones de tramo, las tarjetas de la
     * pizarra y las tarjetas confirmadas en los tickets ya no se usan.
     * No tiene vuelta atrás: las rutas son ahora la única fuente.
     */
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linea_regla_abastecimiento_id');
        });

        Schema::dropIfExists('dotaciones_tramo');
        Schema::dropIfExists('lineas_regla_abastecimiento');
        Schema::dropIfExists('reglas_abastecimiento');
        Schema::dropIfExists('opciones_tramo');
    }

    public function down(): void
    {
        // Se corrige hacia adelante: los datos viven en `rutas`.
    }
};
