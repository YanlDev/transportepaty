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
        Schema::create('estados_unidad', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');

            // El tracto identifica a la unidad. La carreta y el conductor se
            // copian del día, no se referencian por asignación: si mañana se
            // reasigna la carreta, el estado de hoy debe seguir mostrando con
            // cuál se trabajó.
            $table->foreignId('tracto_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('carreta_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();

            $table->string('tipo_carga')->nullable();
            $table->string('estado_carga')->nullable();
            $table->string('cliente')->nullable();
            $table->string('fase')->nullable();

            // Texto libre: no hay catálogo de puntos contra el cual resolver
            // origen/destino/ubicación, así que se anota tal cual llega.
            $table->string('origen')->nullable();
            $table->string('destino')->nullable();
            $table->string('ubicacion')->nullable();

            // Procedencia por campo —del reporte, deducida o confirmada a mano—
            // en vez de una columna por cada una. Es lo que permite que una
            // reimportación recalcule lo deducido sin tocar lo que ya
            // confirmaste.
            $table->json('origenes')->nullable();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Un estado por unidad y por día: reimportar el día corrige el
            // existente en vez de acumular versiones.
            $table->unique(['tracto_id', 'fecha']);
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estados_unidad');
    }
};
