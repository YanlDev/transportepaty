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

            // Número de la GR-transportista (la que emite Paty). Única para que
            // resubir el mismo PDF actualice el viaje en vez de duplicarlo.
            $table->string('numero_gr')->unique();

            $table->dateTime('fecha_emision');
            $table->date('fecha_traslado');

            // Direcciones tal cual vienen en el PDF, multilínea.
            $table->text('origen');
            $table->text('destino');

            // «Cliente» es el remitente de la GR: quien contrata el flete
            // (coincide con las carpetas GR MINSUR/GR SAN LORENZO/etc. del
            // usuario). El destinatario es el receptor final de la carga, que
            // puede ser una empresa distinta.
            $table->string('cliente');
            $table->string('cliente_ruc')->nullable();
            $table->string('destinatario');
            $table->string('destinatario_ruc')->nullable();

            // Guías de remisión del remitente referenciadas en el documento:
            // list<array{numero: string, ruc: string}>. Puede haber más de una.
            $table->json('guias_remitente')->nullable();

            $table->decimal('peso', 10, 3);
            $table->string('unidad_peso');

            // Texto crudo siempre presente; el FK queda null si la placa o el
            // DNI del conductor no matchean contra el padrón.
            $table->string('placa_tracto');
            $table->string('placa_carreta')->nullable();
            $table->foreignId('tracto_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('carreta_id')->nullable()->constrained('vehiculos')->nullOnDelete();

            $table->string('conductor_nombre');
            $table->string('conductor_dni')->nullable();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('fecha_traslado');
            $table->index('tracto_id');
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
