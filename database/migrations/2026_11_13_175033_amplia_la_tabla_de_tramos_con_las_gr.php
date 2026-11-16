<?php

use App\Enums\EstadoVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Services\TablaDeTramos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La tabla de tramos crece con los recorridos particulares que aparecen en
 * las GR desde julio de 2026. Solo se llena lo que el catálogo ya dice sin
 * duda: Juliaca → Lima cargado 180 («Particular a Lima»). De Cusco, Moquegua
 * e Ilo y Tacna la pizarra solo tiene el total del recorrido (ida cargada
 * más regreso vacío a Juliaca): quedan por definir, con ese total en la nota
 * para repartirlo.
 *
 * Pasan a tramos las rutas cuyo tope no cambia con eso: «Particular a Lima»
 * (180) y las dos vacías, que siguen por definir hasta que la tabla tenga
 * su número.
 */
return new class extends Migration
{
    private const TRAMOS = [
        ['juliaca', 'lima', 180, null, 'Cargado: de «Particular a Lima». 20 GR desde julio.'],
        ['lima', 'cusco', null, null, 'Pizarra: Lima → Cusco → Juliaca vacía = 200 en total; falta repartirlo. 37 GR desde julio.'],
        ['cusco', 'juliaca', null, null, 'Pizarra: Lima → Cusco → Juliaca vacía = 200 en total; falta repartirlo.'],
        ['lima', 'moquegua', null, null, 'Pizarra: Lima → Ilo → Juliaca vacía = 190 en total; falta repartirlo. 31 GR desde julio.'],
        ['moquegua', 'juliaca', null, null, 'Pizarra: con harina desde Ilo son 40 más que vacío. 13 GR desde julio.'],
        ['lima', 'tacna', null, null, 'Pizarra: 60 gal hasta 6 t y 80 gal con más, el recorrido entero. 43 GR desde julio.'],
        ['tacna', 'juliaca', null, null, 'Pizarra: entra en el total de Tacna; falta repartirlo.'],
    ];

    private const RUTAS = ['Particular a Lima', 'Vacía a Lima', 'Vacía a Juliaca'];

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
            $rutaId = DB::table('rutas')->where('nombre', $nombre)->where('por_tramos', false)->value('id');

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
