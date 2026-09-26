<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada aviso que sale por el número de la empresa, con su recorrido:
 * pendiente en la cola, enviado, entregado al celular y leído. Es la
 * constancia de que el conductor recibió el aviso, no solo de que se abrió
 * el chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios_whatsapp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_id')->nullable()->constrained('programaciones')->nullOnDelete();
            $table->foreignId('area_aviso_id')->nullable()->constrained('areas_aviso')->nullOnDelete();
            // conductor | advertencia | area
            $table->string('tipo', 20);
            // A quién, como se muestra: «Conductor», «Facturación»…
            $table->string('destino', 60);
            $table->string('numero', 20);
            $table->string('estado', 20)->index();
            // El id que WhatsApp le da al mensaje: con él llegan los ✓✓.
            $table->string('mensaje_id', 100)->nullable()->index();
            $table->text('error')->nullable();
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->index(['programacion_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_whatsapp');
    }
};
