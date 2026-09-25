<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El flete acordado para esa salida, en soles.
 *
 * Es opcional porque no siempre se cierra el precio al programar: cuando no
 * está, el aviso a facturación lo dice en vez de mandar un monto inventado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->decimal('precio_flete', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->dropColumn('precio_flete');
        });
    }
};
