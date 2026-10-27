<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El historial de movimientos del sistema: cada alta, cambio, anulación
     * o baja de un registro, con quién, cuándo y qué valores tenía antes y
     * después. Solo se agrega: nunca se edita ni se borra.
     */
    public function up(): void
    {
        Schema::create('historial_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accion', 20);
            $table->string('modelo', 60);
            $table->unsignedBigInteger('modelo_id');
            $table->string('etiqueta', 160)->nullable();
            $table->json('cambios')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['modelo', 'modelo_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_cambios');
    }
};
