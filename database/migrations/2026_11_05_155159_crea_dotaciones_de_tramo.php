<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tarjeta de la pizarra que alguien confirmó para cada tramo de un
     * ticket, copiada tal cual estaba: si después se cambia la pizarra, lo
     * autorizado para los tickets en ruta no se mueve.
     */
    public function up(): void
    {
        Schema::create('dotaciones_tramo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vuelta_id')->constrained('vueltas')->cascadeOnDelete();
            $table->string('sentido', 10);
            $table->foreignId('regla_abastecimiento_id')->nullable()->constrained('reglas_abastecimiento')->nullOnDelete();
            $table->string('nombre', 100);
            $table->string('color', 20)->nullable();
            // Las líneas de la tarjeta al confirmarla: tipo, concepto, punto,
            // galones y monto.
            $table->json('lineas');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['vuelta_id', 'sentido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotaciones_tramo');
    }
};
