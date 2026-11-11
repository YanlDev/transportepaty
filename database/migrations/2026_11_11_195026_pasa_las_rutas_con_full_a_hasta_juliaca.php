<?php

use App\Enums\EstadoVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * En las rutas con tanque full en Kio, el tope es el del recorrido entero
 * hasta Juliaca y el full descuenta de ahí con su voucher. Se decía con un
 * grifo («Arequipa 190», «Nazca 200») y parecía que todo se carga ahí: pasa
 * a una línea sin grifo, «Hasta Juliaca». Los complementos (Huaral, Uraca)
 * se siguen sumando aparte.
 *
 * Moquegua – Ilo no tenía galones (tope 0): se le ponen 190 como a
 * Arequipa, a revisar cuando se sepa.
 *
 * Los tickets abiertos con estas rutas y sin combustible en ese tramo toman
 * la ruta nueva; los que ya tienen combustible no se tocan.
 */
return new class extends Migration
{
    /** @var array<string, int> */
    private const HASTA_JULIACA = [
        'Particular a Juliaca' => 190,
        'Particular a Arequipa' => 190,
        'Particular a Cusco' => 200,
        'Particular a Moquegua – Ilo' => 190,
    ];

    private const CONCEPTO = 'Hasta Juliaca';

    public function up(): void
    {
        $kio = DB::table('puntos_abastecimiento')->where('nombre', 'Kio')->value('id');

        foreach (self::HASTA_JULIACA as $nombre => $galones) {
            $rutaId = DB::table('rutas')->where('nombre', $nombre)->value('id');

            if ($rutaId === null) {
                continue;
            }

            DB::transaction(function () use ($rutaId, $galones, $kio): void {
                $combustible = fn () => DB::table('lineas_ruta')
                    ->where('ruta_id', $rutaId)
                    ->whereNull('variante_ruta_id')
                    ->where('tipo', 'combustible');

                $full = $combustible()->where('tanque_lleno', true)->first();

                if ($full === null) {
                    DB::table('lineas_ruta')->insert([
                        'ruta_id' => $rutaId,
                        'tipo' => 'combustible',
                        'concepto' => 'Tanque full',
                        'punto_abastecimiento_id' => $kio,
                        'galones' => null,
                        'tanque_lleno' => true,
                        'orden' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } elseif ($full->punto_abastecimiento_id === null && $kio !== null) {
                    DB::table('lineas_ruta')->where('id', $full->id)->update(['punto_abastecimiento_id' => $kio, 'updated_at' => now()]);
                }

                $otras = $combustible()->where('tanque_lleno', false)->orderBy('orden')->get();
                $linea = [
                    'concepto' => self::CONCEPTO,
                    'punto_abastecimiento_id' => null,
                    'galones' => $galones,
                    'updated_at' => now(),
                ];

                if ($otras->isEmpty()) {
                    DB::table('lineas_ruta')->insert([
                        ...$linea,
                        'ruta_id' => $rutaId,
                        'tipo' => 'combustible',
                        'tanque_lleno' => false,
                        'orden' => (int) DB::table('lineas_ruta')->where('ruta_id', $rutaId)->max('orden') + 1,
                        'created_at' => now(),
                    ]);

                    return;
                }

                // Una sola línea con el tope del recorrido: la primera se
                // convierte y las demás se van (eran el mismo tope repartido).
                DB::table('lineas_ruta')->where('id', $otras->first()->id)->update($linea);
                DB::table('lineas_ruta')->whereIn('id', $otras->skip(1)->pluck('id'))->delete();
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
    }

    public function down(): void
    {
        // Es una corrección del catálogo: no se deshace.
    }
};
