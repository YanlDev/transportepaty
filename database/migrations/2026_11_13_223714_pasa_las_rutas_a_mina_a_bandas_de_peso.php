<?php

use App\Enums\EstadoVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Services\TablaDeTramos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lima → Mina cargado gasta según las toneladas: las seis bandas que tenían
 * escritas «Materiales a mina (Ransa)» y «Particular a mina» (110, 120, 130,
 * 150, 190 y 190 gal) pasan a la tabla de tramos, y las dos rutas pasan a
 * tramos. Sus variantes se conservan (mismos pesos, mismos ids): ahora salen
 * de la tabla. Ningún tope cambia.
 */
return new class extends Migration
{
    private const BANDAS = [
        ['peso_desde' => null, 'peso_hasta' => 5.0, 'galones' => 110.0],
        ['peso_desde' => 5.0, 'peso_hasta' => 8.0, 'galones' => 120.0],
        ['peso_desde' => 8.0, 'peso_hasta' => 15.0, 'galones' => 130.0],
        ['peso_desde' => 15.0, 'peso_hasta' => 20.0, 'galones' => 150.0],
        ['peso_desde' => 20.0, 'peso_hasta' => 25.0, 'galones' => 190.0],
        ['peso_desde' => 25.0, 'peso_hasta' => null, 'galones' => 190.0],
    ];

    private const RUTAS = ['Materiales a mina (Ransa)', 'Particular a mina'];

    public function up(): void
    {
        $fila = DB::table('galones_por_tramo')->where('zona_origen', 'lima')->where('zona_destino', 'mina')->first();

        if ($fila === null) {
            DB::table('galones_por_tramo')->insert([
                'zona_origen' => 'lima',
                'zona_destino' => 'mina',
                'bandas' => json_encode(self::BANDAS),
                'nota' => 'Cargado según toneladas: la tabla de Ransa. Alcanza hasta Juliaca.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($fila->bandas === null && $fila->galones_cargado === null) {
            DB::table('galones_por_tramo')->where('id', $fila->id)->update(['bandas' => json_encode(self::BANDAS), 'updated_at' => now()]);
        }

        app(TablaDeTramos::class)->olvidar();

        foreach (self::RUTAS as $nombre) {
            $ruta = Ruta::query()->where('nombre', $nombre)->where('por_tramos', false)->first();

            if ($ruta === null) {
                continue;
            }

            DB::transaction(function () use ($ruta): void {
                $ruta->update(['por_tramos' => true]);
                DB::table('lineas_ruta')
                    ->where('ruta_id', $ruta->id)
                    ->where('tipo', 'combustible')
                    ->where('tanque_lleno', false)
                    ->delete();
                $ruta->sincronizarVariantesConLaTabla();
            });

            $ruta = Ruta::query()->with(['lineas.punto', 'variantes'])->findOrFail($ruta->id);

            RutaVuelta::query()
                ->where('ruta_id', $ruta->id)
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
