<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo se avisa cada tipo de alerta: si está prendida, por qué canales
     * (la campanita de la app, WhatsApp) y a quién. Los tipos viven en código
     * (`TipoAlerta`); acá solo lo que el admin ajusta. Un tipo sin fila usa
     * los valores por defecto de su enum.
     */
    public function up(): void
    {
        Schema::create('configuracion_alertas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40)->unique();
            $table->boolean('activa')->default(true);
            $table->boolean('por_app')->default(true);
            $table->boolean('por_whatsapp')->default(false);
            // A quién: por rol, usuarios sueltos y números de área de WhatsApp.
            $table->json('roles')->nullable();
            $table->json('usuarios')->nullable();
            $table->json('areas')->nullable();
            // Solo los resúmenes diarios: a qué hora (de Lima) salen, y
            // cuándo salió el último para no repetirlo en el día.
            $table->string('hora', 5)->nullable();
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_alertas');
    }
};
