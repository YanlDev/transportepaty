<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que taller pide reponer del equipamiento de una unidad. Es solo el
     * pedido: lo que se entregó se lee de los vales de salida a nombre de la
     * unidad hechos después, así que no guarda si ya se atendió.
     */
    public function up(): void
    {
        Schema::create('reposiciones_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('item_equipamiento_id')->constrained('items_equipamiento')->restrictOnDelete();
            $table->unsignedSmallInteger('cantidad');
            $table->string('nota', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['vehiculo_id', 'item_equipamiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reposiciones_equipamiento');
    }
};
