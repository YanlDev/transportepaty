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
            // Hacia dónde va la unidad (Callao, Pisco, mina...). Texto libre:
            // no hay catálogo de ubicaciones en esta app (se sacó a propósito
            // en el pivote de agosto), así que se anota tal como llega el aviso.
            $table->string('destino')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table) {
            $table->dropColumn('destino');
        });
    }
};
