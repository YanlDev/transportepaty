<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La vuelta de una unidad —el ticket del viaje— y sus paradas.
     *
     * - `vueltas`: de la subida a la bajada. Tracto, carreta y conductor van
     *   copiados: si mañana se reasigna la carreta, la vuelta pasada sigue
     *   diciendo con cuál salió.
     * - `paradas_vuelta`: cada trayecto de la ida o del regreso, con carga o
     *   vacío. Las GR cuelgan de la parada (`viajes.parada_vuelta_id`). Las
     *   zonas son las del circuito (`ZonaRuta`); el lugar, el distrito tal
     *   como lo dice la GR.
     */
    public function up(): void
    {
        Schema::create('vueltas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable()->unique();
            $table->foreignId('tracto_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('carreta_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->string('tipo', 20);
            $table->string('estado', 20);
            $table->string('origen', 20);
            $table->date('inicio')->nullable();
            $table->date('fin')->nullable();
            $table->timestamps();

            $table->index(['tracto_id', 'inicio']);
            $table->index(['estado', 'origen']);
        });

        Schema::create('paradas_vuelta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vuelta_id')->constrained('vueltas')->cascadeOnDelete();
            $table->string('sentido', 10);
            $table->unsignedSmallInteger('orden');
            $table->string('zona_origen', 20)->nullable();
            $table->string('zona_destino', 20)->nullable();
            $table->string('lugar_origen', 100)->nullable();
            $table->string('lugar_destino', 100)->nullable();
            $table->string('tipo_carga', 20);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente')->nullable();
            // Un vacío no tiene GR que diga cuándo salió: queda sin fecha.
            $table->date('fecha')->nullable();
            $table->timestamps();

            $table->index(['vuelta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paradas_vuelta');
        Schema::dropIfExists('vueltas');
    }
};
