<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los viáticos depositados a cada ticket: los de su tarjeta de la
     * pizarra (con el monto previsto) y los imprevistos. No se borran: un
     * depósito mal hecho se anula con su motivo.
     */
    public function up(): void
    {
        Schema::create('viaticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vuelta_id')->constrained('vueltas')->restrictOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->foreignId('linea_regla_abastecimiento_id')->nullable()
                ->constrained('lineas_regla_abastecimiento')->nullOnDelete();
            $table->string('concepto', 120);
            $table->decimal('monto_previsto', 10, 2)->nullable();
            $table->decimal('monto', 10, 2);
            $table->date('fecha');
            $table->string('numero_operacion', 40)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index(['vuelta_id', 'anulado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viaticos');
    }
};
