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
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();

            // El código es el identificador estable del punto: es lo que citan
            // las reglas de carga-ruta, que no pueden depender de un id
            // autoincremental ni del nombre, que puede corregirse.
            $table->string('codigo', 60)->unique();

            $table->string('nombre');

            // Copia del nombre sin tildes, en mayúsculas y sin puntuación, para
            // resolver lo que viene del Excel, donde el mismo punto aparece
            // escrito de varias formas.
            $table->string('nombre_normalizado')->unique();

            // Nulas mientras el punto no tenga posición confirmada. Una
            // ubicación sin coordenadas se importa igual pero no sale en el
            // mapa: un camión mal ubicado es peor que uno sin ubicar.
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            // Zona desde la que una unidad descargada puede entrar a la
            // programación de subida a mina. Hoy solo Juliaca, que es la base
            // de la empresa; se deja como bandera y no como caso único porque
            // es un criterio operativo que puede abrirse a otros puntos.
            $table->boolean('es_zona_base')->default(false);

            // Juliaca es la base de la empresa y la única con taller propio.
            // Una unidad detenida ahí puede estar en mantenimiento, que es
            // normal; detenida en cualquier otro punto, no.
            $table->boolean('tiene_taller')->default(false);

            // Días que una unidad puede quedarse en el punto sin que eso sea
            // una anomalía. En Juliaca son uno o dos, esperando turno para
            // volver a subir. Nulo donde no hay permanencia habitual: ahí una
            // unidad parada varios días es algo que mirar.
            $table->unsignedSmallInteger('dias_permanencia_habitual')->nullable();

            // Posición del punto sobre el corredor troncal, de San Rafael hacia
            // el norte. Numerado de diez en diez para poder intercalar puntos
            // nuevos sin renumerar el resto. Nulo en los destinos que quedan
            // fuera del corredor, como Arequipa o Cusco: esos son válidos como
            // ubicación pero no participan del cálculo de avance ni de llegada.
            $table->unsignedSmallInteger('orden_corredor')->nullable();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('es_zona_base');
            $table->index('orden_corredor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubicaciones');
    }
};
