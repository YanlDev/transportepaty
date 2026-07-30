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
        Schema::create('ubicacion_alias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->cascadeOnDelete();

            // Cada vez que se confirma a mano que un texto del Excel es un punto
            // conocido, la equivalencia se guarda acá y no se vuelve a preguntar.
            // Así el catálogo aprende de las correcciones y la cola de
            // ubicaciones sin resolver se vacía sola en pocas semanas.
            $table->string('nombre_normalizado')->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubicacion_alias');
    }
};
