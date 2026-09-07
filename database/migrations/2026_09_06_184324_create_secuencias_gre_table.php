<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secuencias_gre', function (Blueprint $table) {
            // Una fila por serie. SUNAT exige correlativos sin huecos ni
            // repetidos, así que el número no se calcula con un max() sobre
            // las guías —dos emisiones simultáneas sacarían el mismo— sino
            // que se reserva bloqueando esta fila.
            $table->string('serie', 4)->primary();
            $table->unsignedInteger('ultimo_correlativo')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias_gre');
    }
};
