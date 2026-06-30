<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            // Punto de partida desde el cual se cuenta la distancia del track GPS
            // para avanzar el odómetro en cada sincronización.
            $table->timestamp('odometro_sincronizado_en')->nullable()->after('km_calibrado_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('odometro_sincronizado_en');
        });
    }
};
