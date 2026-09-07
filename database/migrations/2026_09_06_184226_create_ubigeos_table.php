<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table) {
            // El código INEI de 6 dígitos es la identidad: es lo que viaja en
            // el XML de la guía, y no hay dos distritos con el mismo.
            $table->char('codigo', 6)->primary();

            $table->string('distrito');
            $table->string('provincia');
            $table->string('departamento');

            // Los tres nombres juntos, en mayúsculas y sin tildes, para buscar
            // escribiendo «antauta» o «melgar puno» sin pelear con los acentos.
            $table->string('busqueda')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubigeos');
    }
};
