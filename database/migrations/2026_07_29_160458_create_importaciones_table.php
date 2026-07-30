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
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();

            // El día que reporta el archivo, no el día en que se sube: puede
            // cargarse tarde y eso no debe correr la fecha de la disponibilidad.
            $table->date('fecha');

            $table->string('archivo_original');
            $table->foreignId('subido_por')->constrained('users')->cascadeOnDelete();

            // Se marca cuando el usuario aprueba la previsualización y las filas
            // resueltas pasan a escribirse en el estado canónico. Antes de eso,
            // el archivo no ha tocado nada: es una previsualización nada más.
            $table->timestamp('confirmado_en')->nullable();

            $table->unsignedSmallInteger('filas_totales')->default(0);
            $table->unsignedSmallInteger('filas_resueltas')->default(0);

            $table->timestamps();

            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
