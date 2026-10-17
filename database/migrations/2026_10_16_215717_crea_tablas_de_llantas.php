<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las llantas de cada unidad, por posición (ver `PosicionesLlanta`).
     *
     * - `llantas`: cada llanta mientras está montada; al retirarla queda con
     *   su fecha de retiro, y la posición queda libre para la siguiente. Una
     *   rotación solo le cambia la `posicion`.
     * - `movimientos_llanta`: todo lo que se le hizo —instalarla, medirla,
     *   rotarla, repararla, retirarla—. La profundidad del dibujo se anota en
     *   el movimiento y el semáforo sale de la última.
     */
    public function up(): void
    {
        Schema::create('llantas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('posicion', 4);
            $table->string('marca')->nullable();
            $table->string('medida', 50)->nullable();
            $table->string('codigo', 100)->nullable();
            $table->string('condicion', 20);
            $table->date('instalada_el');
            $table->unsignedInteger('km_instalacion')->nullable();
            $table->date('retirada_el')->nullable();
            $table->unsignedInteger('km_retiro')->nullable();
            $table->string('motivo_retiro', 20)->nullable();
            $table->timestamps();

            $table->index(['vehiculo_id', 'retirada_el']);
        });

        Schema::create('movimientos_llanta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llanta_id')->constrained('llantas')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->date('fecha');
            // Dónde estaba la llanta; en una rotación, también a dónde pasó.
            $table->string('posicion', 4);
            $table->string('posicion_destino', 4)->nullable();
            $table->unsignedInteger('kilometraje')->nullable();
            $table->decimal('profundidad_mm', 4, 1)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha']);
            $table->index(['llanta_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_llanta');
        Schema::dropIfExists('llantas');
    }
};
