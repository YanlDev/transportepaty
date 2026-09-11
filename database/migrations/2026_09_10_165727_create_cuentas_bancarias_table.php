<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();

            // El banco va como texto y no como enum: la lista real la
            // administra el usuario desde la app, y encajonarla en código
            // obligaría a una migración cada vez que abren una cuenta nueva.
            $table->string('banco');

            // Alias corto para la tabla de contabilidad: «BCP Soles» entra en
            // una celda, «Banco de Crédito del Perú - Cta. Cte. Soles» no.
            $table->string('alias');

            $table->string('numero_cuenta');

            // Código de cuenta interbancario. Nullable porque no todas las
            // cuentas lo tienen a la mano cuando se dan de alta.
            $table->string('cci')->nullable();

            $table->string('moneda', 3)->default('PEN');

            // Las cuentas cerradas no se borran: las facturas ya cobradas por
            // ellas tienen que seguir mostrando por dónde entró la plata.
            $table->boolean('activa')->default(true);

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['banco', 'numero_cuenta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
