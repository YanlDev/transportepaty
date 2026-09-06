<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            // El RUC es la identidad real del cliente: la razón social llega
            // escrita distinto según quién emitió la GR, el RUC no.
            $table->string('ruc', 11)->unique();
            $table->string('razon_social');
            // Nombre corto para las tablas y gráficos: la razón social
            // completa («... SOCIEDAD ANONIMA CERRADA») no entra en ninguna
            // columna sin romper el ancho.
            $table->string('alias');
            $table->string('contacto')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            // Con quién se trabaja seguido, para separarlo del cliente que
            // apareció una sola vez.
            $table->boolean('recurrente')->default(false);
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
