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
        Schema::create('viajes', function (Blueprint $table) {
            $table->id();

            // Los fierros y el conductor van copiados, no referenciados por
            // asignación: si mañana se reasigna la carreta, el viaje del mes
            // pasado tiene que seguir mostrando con cuál se hizo.
            $table->foreignId('tracto_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('carreta_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();

            $table->string('tipo_carga');
            $table->string('fase')->nullable();

            $table->foreignId('origen_id')->constrained('ubicaciones')->cascadeOnDelete();
            $table->foreignId('destino_id')->constrained('ubicaciones')->cascadeOnDelete();

            // Llegada nula significa viaje en curso.
            $table->date('fecha_salida');
            $table->date('fecha_llegada')->nullable();

            // Las dos guías del traslado: la del remitente la emite el cliente
            // por la mercadería, la del transportista la emite la empresa.
            $table->string('numero_guia_remitente', 60)->nullable();
            $table->string('numero_guia_transportista', 60)->nullable();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('fecha_salida');
            $table->index('fecha_llegada');
            $table->index('numero_guia_remitente');
            $table->index('numero_guia_transportista');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viajes');
    }
};
