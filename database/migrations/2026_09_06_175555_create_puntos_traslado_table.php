<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puntos_traslado', function (Blueprint $table) {
            $table->id();

            // Nombre corto con el que el usuario lo elige ("Planta Paracas",
            // "Mina San Rafael"). La dirección larga va aparte porque SUNAT la
            // quiere completa y nadie la reconoce de un vistazo.
            $table->string('nombre');

            // Los dos datos que la GRE exige por punto y que hoy no existen:
            // el ubigeo INEI de 6 dígitos y la dirección tal cual se declara.
            $table->char('ubigeo', 6);
            $table->text('direccion');

            // Establecimiento anexo del contribuyente dueño del local. Es
            // opcional en la GRE: se declara como AddressTypeCode y solo tiene
            // sentido con el RUC al que pertenece.
            $table->string('ruc', 11)->nullable();
            $table->string('cod_local', 4)->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_traslado');
    }
};
