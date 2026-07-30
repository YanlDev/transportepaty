<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `orden_corredor` deja de ser una posición única y pasa a ser el número de
     * zona: varias ubicaciones lo comparten. Los reportes reales muestran que el
     * mismo tramo se hace a veces por La Joya, a veces por Majes y a veces por
     * Yura y Arequipa, así que tratarlo como una fila recta marcaba como
     * imposibles trayectos que son normales.
     *
     * Para poder seguir midiendo distancias hace falta un punto de referencia
     * por zona —el pueblo grande sobre la carretera—, y eso es el eje.
     */
    public function up(): void
    {
        Schema::table('ubicaciones', function (Blueprint $table) {
            $table->boolean('es_eje_corredor')->default(false)->after('orden_corredor');
            $table->index('es_eje_corredor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ubicaciones', function (Blueprint $table) {
            $table->dropIndex(['es_eje_corredor']);
            $table->dropColumn('es_eje_corredor');
        });
    }
};
