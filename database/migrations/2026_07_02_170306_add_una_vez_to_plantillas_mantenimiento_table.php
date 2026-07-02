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
        Schema::table('plantillas_mantenimiento', function (Blueprint $table) {
            // Servicio único (primer mantenimiento): se muestra una sola vez
            // (a `intervalo_km`, p. ej. 1000) y no recurre una vez realizado.
            $table->boolean('una_vez')->default(false)->after('intervalo_meses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plantillas_mantenimiento', function (Blueprint $table) {
            $table->dropColumn('una_vez');
        });
    }
};
