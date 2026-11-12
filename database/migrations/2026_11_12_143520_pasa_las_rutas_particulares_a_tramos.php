<?php

use App\Enums\EstadoVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Services\TablaDeTramos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Galones por tramo: la tabla arranca con lo que se sabe (Lima → Arequipa
 * cargado 150; Arequipa → Juliaca vacío 20 y cargado 40, que sale de
 * 190 − 150 y está por confirmar; Lima → Juliaca cargado 190) y pasan a
 * tramos las dos rutas que ya tienen todos sus tramos en la tabla:
 * «Particular a Arequipa» (150 + 20 = 170, antes 190) y «Particular a
 * Juliaca» (190). Sus líneas de combustible escritas a mano se van (el full
 * de Kio y los viáticos se quedan).
 *
 * Los tickets abiertos con esas rutas pasan también a tramos: su tope sale
 * de sus paradas reales.
 */
return new class extends Migration
{
    private const TRAMOS = [
        ['lima', 'arequipa', 150, null, null],
        ['arequipa', 'juliaca', 40, 20, 'Cargado: 190 − 150, a confirmar.'],
        ['lima', 'juliaca', 190, null, null],
    ];

    private const RUTAS = ['Particular a Arequipa', 'Particular a Juliaca'];

    public function up(): void
    {
        foreach (self::TRAMOS as [$origen, $destino, $cargado, $vacio, $nota]) {
            if (DB::table('galones_por_tramo')->where('zona_origen', $origen)->where('zona_destino', $destino)->exists()) {
                continue;
            }

            DB::table('galones_por_tramo')->insert([
                'zona_origen' => $origen,
                'zona_destino' => $destino,
                'galones_cargado' => $cargado,
                'galones_vacio' => $vacio,
                'nota' => $nota,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(TablaDeTramos::class)->olvidar();

        foreach (self::RUTAS as $nombre) {
            $rutaId = DB::table('rutas')->where('nombre', $nombre)->value('id');

            if ($rutaId === null) {
                continue;
            }

            DB::transaction(function () use ($rutaId): void {
                DB::table('rutas')->where('id', $rutaId)->update(['por_tramos' => true, 'updated_at' => now()]);
                DB::table('lineas_ruta')
                    ->where('ruta_id', $rutaId)
                    ->where('tipo', 'combustible')
                    ->where('tanque_lleno', false)
                    ->delete();
            });

            $ruta = Ruta::query()->with(['lineas.punto', 'variantes'])->find($rutaId);

            RutaVuelta::query()
                ->where('ruta_id', $rutaId)
                ->whereHas('vuelta', fn ($query) => $query->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso]))
                ->get()
                ->each(fn (RutaVuelta $copia) => $copia->update([
                    'por_tramos' => true,
                    'lineas' => $ruta->lineasPara($ruta->variantes->firstWhere('id', $copia->variante_ruta_id)),
                ]));
        }
    }

    public function down(): void
    {
        // La dotación por tramos reemplaza a la escrita a mano: no se repone.
    }
};
