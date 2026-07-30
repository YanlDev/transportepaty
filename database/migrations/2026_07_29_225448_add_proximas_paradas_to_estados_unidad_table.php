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
        Schema::table('estados_unidad', function (Blueprint $table) {
            // Notas libres del plan de un viaje particular (varias paradas,
            // variable de viaje en viaje) — a diferencia del corredor de mina,
            // que es fijo y no necesita anotarse.
            $table->text('proximas_paradas')->nullable()->after('observaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estados_unidad', function (Blueprint $table) {
            $table->dropColumn('proximas_paradas');
        });
    }
};
