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
            // Vistazo rápido del último cambio de estado dentro del mismo día
            // (ej. Metálico → Libre a las 14:32), sin llevar un historial
            // completo de cada cambio: solo el inmediato anterior.
            $table->string('estado_anterior')->nullable()->after('estado');
            $table->timestamp('estado_cambiado_en')->nullable()->after('estado_anterior');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table) {
            $table->dropColumn(['estado_anterior', 'estado_cambiado_en']);
        });
    }
};
