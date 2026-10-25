<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una GR que llega a una vuelta programada y no calza con ninguna de sus
     * paradas entra igual, como parada «no programada»: así se ve qué salió
     * distinto de lo planeado.
     */
    public function up(): void
    {
        Schema::table('paradas_vuelta', function (Blueprint $table) {
            $table->boolean('fuera_de_programa')->default(false)->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('paradas_vuelta', function (Blueprint $table) {
            $table->dropColumn('fuera_de_programa');
        });
    }
};
