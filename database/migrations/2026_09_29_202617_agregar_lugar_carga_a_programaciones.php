<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dónde carga la unidad, escrito a mano. Hasta ahora el aviso lo deducía de
 * las GR anteriores del cliente —la ciudad de partida más frecuente—, y eso
 * falla cuando el cliente carga en otro sitio esta vez (Ceramics Import salía
 * «COMAS»). Vacío, se sigue deduciendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->string('lugar_carga')->nullable()->after('destino');
        });
    }

    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->dropColumn('lugar_carga');
        });
    }
};
