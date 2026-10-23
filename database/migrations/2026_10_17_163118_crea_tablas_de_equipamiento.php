<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El equipamiento de las carretas, por cantidad: no se sigue cada faja,
     * se cuenta cuántas hay.
     *
     * - `items_equipamiento`: el catálogo, con el mínimo que toda carreta
     *   debe llevar (igual para todas). Sin mínimo, el ítem se cuenta pero no
     *   avisa faltantes.
     * - `conteos_equipamiento`: cada vez que se cuenta en base. Lo que tiene
     *   la carreta es lo de su último conteo.
     * - `detalles_conteo_equipamiento`: cuántas buenas y cuántas dañadas de
     *   cada ítem en ese conteo. Las dañadas no cuentan para el mínimo.
     *
     * Se siembran las fajas, de 4" y de 2", con y sin chicharra.
     */
    public function up(): void
    {
        Schema::create('items_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedSmallInteger('minimo')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('conteos_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha']);
        });

        Schema::create('detalles_conteo_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_equipamiento_id')->constrained('conteos_equipamiento')->cascadeOnDelete();
            $table->foreignId('item_equipamiento_id')->constrained('items_equipamiento')->cascadeOnDelete();
            $table->unsignedSmallInteger('buenas')->default(0);
            $table->unsignedSmallInteger('danadas')->default(0);
            $table->timestamps();

            $table->unique(['conteo_equipamiento_id', 'item_equipamiento_id']);
        });

        $ahora = now();

        DB::table('items_equipamiento')->insert(array_map(fn (string $nombre, int $orden): array => [
            'nombre' => $nombre,
            'minimo' => null,
            'orden' => $orden,
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], [
            'Faja de 4" con chicharra',
            'Faja de 4" sin chicharra',
            'Faja de 2" con chicharra',
            'Faja de 2" sin chicharra',
        ], [1, 2, 3, 4]));
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_conteo_equipamiento');
        Schema::dropIfExists('conteos_equipamiento');
        Schema::dropIfExists('items_equipamiento');
    }
};
