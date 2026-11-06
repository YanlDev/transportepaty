<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La salida programada es la ida particular de una vuelta: programar la
     * unidad es lo que hace nacer el ticket (ver `ProgramadorVueltas`).
     */
    public function up(): void
    {
        Schema::table('programaciones', function (Blueprint $table) {
            $table->foreignId('parada_vuelta_id')->nullable()->after('cliente_id')
                ->constrained('paradas_vuelta')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parada_vuelta_id');
        });
    }
};
