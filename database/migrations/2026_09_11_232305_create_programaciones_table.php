<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué unidad sale con carga particular, qué día, para qué cliente y a qué
 * destino. Se llena ANTES de que exista la guía de remisión: es el plan, no
 * el registro del viaje.
 *
 * Reusa el nombre de la tabla que se eliminó el 2026-08-22 (el tablero de
 * programación viejo), pero no tiene nada que ver con aquella: son cinco
 * campos y ninguna regla de elegibilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programaciones', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');

            // `restrictOnDelete` y no cascada: borrar un vehículo o un
            // conductor no puede hacer desaparecer en silencio la
            // programación de un día que ya se comunicó a abastecimiento.
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();

            // Texto libre a propósito: no hay catálogo de destinos (la tabla
            // `puntos_traslado` está vacía) y en la práctica el destino se
            // dicta por WhatsApp como un nombre suelto («Ilave», «Juliaca»).
            $table->string('destino');

            $table->timestamps();

            // El listado siempre entra por día.
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programaciones');
    }
};
