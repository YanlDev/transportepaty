<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La llegada que nadie registró y el sistema dedujo porque la unidad
     * volvió a salir: se distingue de la que anotó una persona.
     */
    public function up(): void
    {
        Schema::table('vueltas', function (Blueprint $table) {
            $table->boolean('llegada_deducida')->default(false)->after('llegada_por');
        });
    }

    public function down(): void
    {
        Schema::table('vueltas', function (Blueprint $table) {
            $table->dropColumn('llegada_deducida');
        });
    }
};
