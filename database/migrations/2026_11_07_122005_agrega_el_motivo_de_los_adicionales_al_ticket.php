<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un adicional del tramo: galones extra solo para ese ticket (un desvío
     * que no se repite, como descargar en Lurín antes de subir). Es una
     * ruta más del tramo, sin ruta del catálogo detrás, con su motivo.
     */
    public function up(): void
    {
        Schema::table('rutas_vuelta', function (Blueprint $table) {
            $table->string('motivo', 200)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('rutas_vuelta', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
