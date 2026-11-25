<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El levantamiento del equipamiento, unidad por unidad y con fotos.
     *
     * - `detalles_conteo_equipamiento.nota`: lo que se vio de ese ítem
     *   («manómetro en rojo», «le falta la tapa»). Las fotos van aparte, en
     *   la biblioteca de archivos.
     * - La dotación básica: lo que debe llevar un tracto queda con su
     *   cantidad (solo donde nadie la había definido) y se suman los ítems
     *   que faltaban. Las fajas de la carreta siguen sin cantidad: se
     *   cuentan, pero todavía no avisan faltantes.
     *
     * La lista se cambia después desde la pantalla («Dotación»).
     */
    public function up(): void
    {
        Schema::table('detalles_conteo_equipamiento', function (Blueprint $table) {
            $table->string('nota', 300)->nullable()->after('danadas');
        });

        $ahora = now();

        foreach ([
            'Extintor' => 1,
            'Botiquín' => 1,
            'Conos de seguridad' => 2,
            'Kit antiderrame' => 1,
            'Pico' => 1,
            'Pala' => 1,
            'Caja de herramientas' => 1,
        ] as $nombre => $cantidad) {
            DB::table('items_equipamiento')
                ->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])
                ->whereNull('minimo')
                ->update(['minimo' => $cantidad, 'updated_at' => $ahora]);
        }

        $orden = (int) DB::table('items_equipamiento')->max('orden');

        foreach ([
            'Triángulos de seguridad' => 2,
            'Tacos de seguridad' => 2,
            'Gata' => 1,
            'Llave de ruedas' => 1,
        ] as $nombre => $cantidad) {
            if (DB::table('items_equipamiento')->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->exists()) {
                continue;
            }

            DB::table('items_equipamiento')->insert([
                'nombre' => $nombre,
                'aplica_a' => 'tracto',
                'lleva_vencimiento' => false,
                'minimo' => $cantidad,
                'orden' => ++$orden,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('detalles_conteo_equipamiento', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
    }
};
