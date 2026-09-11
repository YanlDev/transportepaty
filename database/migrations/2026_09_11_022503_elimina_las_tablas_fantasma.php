<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `cotizacions` y `parametro_costos` quedaron de un `make:model -m`: las
 * migraciones buenas terminaron llamándose `cotizaciones` y `parametros_flota`,
 * pero las tablas que creó el generador siguieron en la base. Nunca tuvieron
 * modelo, nadie las consultó y estaban en cero filas al eliminarlas.
 *
 * Sin `down()` que las recree: volver a levantarlas sería reintroducir el
 * defecto, no revertir un cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cotizacions');
        Schema::dropIfExists('parametro_costos');
    }
};
