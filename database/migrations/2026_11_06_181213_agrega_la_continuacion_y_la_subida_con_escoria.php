<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Las rutas cuyo combustible alcanza solo hasta Juliaca. */
    private const RUTAS_A_MINA = ['Escoria a mina', 'Sacos a mina', 'Materiales a mina (Ransa)', 'Particular a mina'];

    private const NOTA_HASTA_JULIACA = 'El combustible alcanza hasta Juliaca: la subida a la mina va en la ida del ticket siguiente.';

    /**
     * Juliaca es la única base: ahí se reinicia el combustible.
     *
     * - Una parada puede seguir con la carga de una GR del ticket anterior
     *   (la escoria que viene del Callao sube de Juliaca a la mina en el
     *   ticket siguiente, con la misma GR).
     * - Un complemento puede sumarse solo cuando la subida a la mina lleva
     *   cierta carga: con escoria la ida pasa de 220 a 230 galones.
     * - La ida de concentrado se carga en Juliaca y Arequipa como quiera el
     *   conductor: lo que manda es el total, no el reparto.
     */
    public function up(): void
    {
        Schema::table('paradas_vuelta', function (Blueprint $table) {
            $table->foreignId('viaje_continuado_id')->nullable()->after('fuera_de_programa')
                ->constrained('viajes')->nullOnDelete();
        });

        Schema::table('rutas', function (Blueprint $table) {
            $table->string('sube_con', 20)->nullable()->after('es_complemento');
        });

        $this->totalDelConcentrado();
        $this->subidaConEscoria();

        DB::table('rutas')
            ->whereIn('nombre', self::RUTAS_A_MINA)
            ->where(fn ($query) => $query->whereNull('nota')->orWhere('nota', 'not like', '%hasta Juliaca%'))
            ->get(['id', 'nota'])
            ->each(fn (object $ruta) => DB::table('rutas')->where('id', $ruta->id)->update([
                'nota' => trim(self::NOTA_HASTA_JULIACA."\n".($ruta->nota ?? '')),
                'updated_at' => now(),
            ]));
    }

    /**
     * «Concentrado a Pisco» pasa de «Mina 100 + Arequipa 120» a un total de
     * 220 entre Juliaca y Arequipa. Si ya se editó, no se toca.
     */
    private function totalDelConcentrado(): void
    {
        $rutaId = DB::table('rutas')->where('nombre', 'Concentrado a Pisco')->value('id');

        if ($rutaId === null) {
            return;
        }

        $lineas = DB::table('lineas_ruta')->where('ruta_id', $rutaId)->where('tipo', 'combustible')->orderBy('orden')->get();

        if ($lineas->count() !== 2 || abs((float) $lineas->sum('galones') - 220) > 0.001) {
            return;
        }

        DB::table('lineas_ruta')->where('id', $lineas[0]->id)->update([
            'concepto' => 'Juliaca y Arequipa (como se reparta)',
            'punto_abastecimiento_id' => null,
            'galones' => 220,
            'updated_at' => now(),
        ]);
        DB::table('lineas_ruta')->where('id', $lineas[1]->id)->delete();
    }

    /**
     * El complemento de la ida que sube con escoria: 10 galones más.
     */
    private function subidaConEscoria(): void
    {
        if (DB::table('rutas')->where('sube_con', 'escoria')->exists()) {
            return;
        }

        $rutaId = DB::table('rutas')->insertGetId([
            'nombre' => 'Sube con escoria',
            'descripcion' => 'Juliaca → mina con la escoria que trae',
            'sentido' => 'ida',
            'es_complemento' => true,
            'sube_con' => 'escoria',
            'paradas' => json_encode([]),
            'pide_destino' => false,
            'nota' => 'Se suma sola cuando la ida sube a la mina con escoria: la ida pasa de 220 a 230 galones.',
            'activa' => true,
            'orden' => (int) DB::table('rutas')->max('orden') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lineas_ruta')->insert([
            'ruta_id' => $rutaId,
            'variante_ruta_id' => null,
            'tipo' => 'combustible',
            'concepto' => 'Subida con escoria',
            'punto_abastecimiento_id' => null,
            'galones' => 10,
            'monto' => null,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('paradas_vuelta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('viaje_continuado_id');
        });

        Schema::table('rutas', function (Blueprint $table) {
            $table->dropColumn('sube_con');
        });
    }
};
