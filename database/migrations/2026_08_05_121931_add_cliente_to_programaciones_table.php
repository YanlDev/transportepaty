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
        Schema::table('programaciones', function (Blueprint $table) {
            // Solo tiene sentido cuando el estado es «particular»: el cliente
            // final del viaje (Promart, Cerámica San Lorenzo...), que cambia
            // de viaje en viaje y no tiene catálogo fijo.
            $table->string('cliente')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table) {
            $table->dropColumn('cliente');
        });
    }
};
