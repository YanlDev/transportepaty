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
        Schema::create('cargas_combustible', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            // Conductor o admin que subió las fotos.
            $table->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();

            // Fecha REAL de la carga (del comprobante). Ordena la cadena de
            // rendimiento; el admin la corrige al procesar.
            $table->timestamp('fecha_carga');

            // Datos que llena el admin al procesar (nulos mientras "por procesar").
            $table->unsignedInteger('odometro')->nullable();
            $table->decimal('galones', 8, 3)->nullable();
            $table->decimal('costo_total', 10, 2)->nullable();
            $table->decimal('precio_por_galon', 8, 3)->nullable();

            $table->text('observaciones')->nullable();

            // Marca de procesamiento: procesada_en NULL = "por procesar".
            $table->foreignId('procesada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('procesada_en')->nullable();

            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha_carga']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargas_combustible');
    }
};
