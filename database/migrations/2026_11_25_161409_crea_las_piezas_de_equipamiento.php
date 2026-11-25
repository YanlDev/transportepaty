<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que se lleva pieza por pieza: cada extintor, botiquín o caja de
     * herramientas con su código propio, como las llantas.
     *
     * - `items_equipamiento.por_pieza` y `prefijo`: qué ítems se identifican
     *   uno por uno y con qué letras empieza su código (EX-001).
     * - `piezas_equipamiento`: cada pieza, en qué unidad está, qué es
     *   (capacidad, serie) y cuándo vence. No cambia cuántas tiene la
     *   unidad: eso lo siguen diciendo el levantamiento y los vales; la
     *   pieza dice cuál es cada una.
     * - `movimientos_equipamiento.pieza_equipamiento_id`: qué pieza se pasó
     *   a otra unidad o se dio de baja.
     */
    public function up(): void
    {
        Schema::table('items_equipamiento', function (Blueprint $table) {
            $table->boolean('por_pieza')->default(false)->after('lleva_vencimiento');
            $table->string('prefijo', 4)->nullable()->unique()->after('por_pieza');
        });

        Schema::create('piezas_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            $table->foreignId('item_equipamiento_id')->constrained('items_equipamiento')->restrictOnDelete();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('descripcion', 120)->nullable();
            $table->string('serie', 60)->nullable();
            $table->date('vence_el')->nullable();
            // El levantamiento que la encontró: si se elimina, la pieza se va con él.
            $table->foreignId('conteo_equipamiento_id')->nullable()->constrained('conteos_equipamiento')->cascadeOnDelete();
            $table->timestamp('baja_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_id', 'item_equipamiento_id']);
        });

        Schema::table('movimientos_equipamiento', function (Blueprint $table) {
            $table->foreignId('pieza_equipamiento_id')->nullable()->after('item_equipamiento_id')->constrained('piezas_equipamiento')->nullOnDelete();
        });

        foreach (['Extintor' => 'EX', 'Botiquín' => 'BO', 'Caja de herramientas' => 'CH'] as $nombre => $prefijo) {
            DB::table('items_equipamiento')
                ->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])
                ->update(['por_pieza' => true, 'prefijo' => $prefijo]);
        }
    }

    public function down(): void
    {
        Schema::table('movimientos_equipamiento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pieza_equipamiento_id');
        });

        Schema::dropIfExists('piezas_equipamiento');

        Schema::table('items_equipamiento', function (Blueprint $table) {
            $table->dropUnique(['prefijo']);
            $table->dropColumn(['por_pieza', 'prefijo']);
        });
    }
};
