<?php

use App\Enums\EstadoVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Particular a Arequipa» (Lima → Arequipa, y vacía a Juliaca) se usa como
 * «Particular a Juliaca»: tanque full en Kio y 190 gal para todo el
 * recorrido; el full descuenta de esos 190 con su voucher (tanquea 144,
 * quedan 46). Estaba con el full en Lima y la línea de Arequipa sin
 * galones (o sin ella): el tramo quedaba sin tope o en 0 y no se podía
 * autorizar lo que falta después de Kio.
 *
 * Los tickets abiertos con esta ruta que todavía no tienen combustible en
 * ese tramo toman la ruta corregida (su copia aún no se usó para medir
 * nada). Los que ya tienen combustible no se tocan.
 */
return new class extends Migration
{
    private const NOMBRE = 'Particular a Arequipa';

    private const GALONES = 190;

    public function up(): void
    {
        $rutaId = DB::table('rutas')->where('nombre', self::NOMBRE)->value('id');

        if ($rutaId === null) {
            return;
        }

        $kio = DB::table('puntos_abastecimiento')->where('nombre', 'Kio')->value('id');
        $arequipa = DB::table('puntos_abastecimiento')->whereRaw('lower(nombre) like ?', ['arequipa%santa rosa%'])->value('id')
            ?? DB::table('puntos_abastecimiento')->where('nombre', 'Arequipa')->value('id');

        DB::transaction(function () use ($rutaId, $kio, $arequipa): void {
            $combustible = DB::table('lineas_ruta')->where('ruta_id', $rutaId)->where('tipo', 'combustible');

            // El full se echa en Kio.
            if ($kio !== null) {
                (clone $combustible)->where('tanque_lleno', true)->update(['punto_abastecimiento_id' => $kio, 'updated_at' => now()]);
            }

            $otras = (clone $combustible)->where('tanque_lleno', false)->get();

            if ($otras->isEmpty()) {
                DB::table('lineas_ruta')->insert([
                    'ruta_id' => $rutaId,
                    'tipo' => 'combustible',
                    'concepto' => 'Arequipa',
                    'punto_abastecimiento_id' => $arequipa,
                    'galones' => self::GALONES,
                    'tanque_lleno' => false,
                    'orden' => (int) DB::table('lineas_ruta')->where('ruta_id', $rutaId)->max('orden') + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($otras->whereNotNull('galones')->sum('galones') == 0) {
                // La línea que quedó «por definir» (o en 0) es la del tope.
                $linea = $otras->first();
                DB::table('lineas_ruta')->where('id', $linea->id)->update([
                    'galones' => self::GALONES,
                    'punto_abastecimiento_id' => $linea->punto_abastecimiento_id ?? $arequipa,
                    'updated_at' => now(),
                ]);
            }
        });

        $ruta = Ruta::query()->with(['lineas.punto', 'variantes'])->find($rutaId);

        RutaVuelta::query()
            ->with('vuelta')
            ->where('ruta_id', $rutaId)
            ->whereHas('vuelta', fn ($query) => $query->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso]))
            ->get()
            ->reject(fn (RutaVuelta $copia): bool => $copia->vuelta->abastecimientos()->vigentes()->where('sentido', $copia->sentido)->exists())
            ->each(fn (RutaVuelta $copia) => $copia->update([
                'lineas' => $ruta->lineasPara($ruta->variantes->firstWhere('id', $copia->variante_ruta_id)),
            ]));
    }

    public function down(): void
    {
        // Es una corrección del catálogo: no se deshace.
    }
};
