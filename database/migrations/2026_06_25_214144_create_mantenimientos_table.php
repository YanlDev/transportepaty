<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantenimientos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->dateTime('fecha_realizado');
            $table->unsignedInteger('odometro');
            $table->string('proveedor')->nullable();
            $table->string('factura_numero')->nullable();
            $table->decimal('costo_total', 10, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehiculo_id', 'fecha_realizado']);
            $table->index(['vehiculo_id', 'odometro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
    }
};
