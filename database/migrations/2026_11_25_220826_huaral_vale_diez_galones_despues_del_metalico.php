<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GALONES = 10;

    /**
     * «Ida y vuelta Huaral» suma 20 gal cuando la unidad baja vacía desde
     * Pisco; si ya llegó al Callao con el metálico (que ya dio 10), solo
     * suma 10. La regla queda en la línea del catálogo y en los tickets que
     * ya tenían Huaral, salvo los que ya recibieron combustible en ese
     * tramo: lo autorizado contra los 20 no se toca.
     */
    public function up(): void
    {
        $huaral = DB::table('rutas')->where('nombre', 'Ida y vuelta Huaral')->value('id');
        $metalico = DB::table('rutas')->where('nombre', 'Metálico al Callao')->value('id');

        if ($huaral === null || $metalico === null) {
            return;
        }

        DB::table('lineas_ruta')
            ->where('ruta_id', $huaral)
            ->where('tipo', 'combustible')
            ->where('tanque_lleno', false)
            ->update(['si_lleva_ruta_id' => $metalico, 'galones_si_la_lleva' => self::GALONES]);

        $this->enLosTickets($huaral, fn (array $linea): array => [...$linea, 'si_lleva_ruta_id' => $metalico, 'galones_si_la_lleva' => self::GALONES]);
    }

    public function down(): void
    {
        $huaral = DB::table('rutas')->where('nombre', 'Ida y vuelta Huaral')->value('id');

        if ($huaral === null) {
            return;
        }

        DB::table('lineas_ruta')->where('ruta_id', $huaral)->update(['si_lleva_ruta_id' => null, 'galones_si_la_lleva' => null]);

        $this->enLosTickets($huaral, function (array $linea): array {
            unset($linea['si_lleva_ruta_id'], $linea['galones_si_la_lleva']);

            return $linea;
        }, soloSinCombustible: false);
    }

    /**
     * Pasa las líneas de combustible de Huaral ya copiadas a los tickets.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $cambio
     */
    private function enLosTickets(int $huaral, callable $cambio, bool $soloSinCombustible = true): void
    {
        $copiadas = DB::table('rutas_vuelta')
            ->where('ruta_id', $huaral)
            ->when($soloSinCombustible, fn ($query) => $query->whereNotExists(fn ($abastecimientos) => $abastecimientos
                ->from('abastecimientos')
                ->whereColumn('abastecimientos.vuelta_id', 'rutas_vuelta.vuelta_id')
                ->whereColumn('abastecimientos.sentido', 'rutas_vuelta.sentido')
                ->whereNull('abastecimientos.anulado_at')))
            ->get(['id', 'lineas']);

        foreach ($copiadas as $copiada) {
            $lineas = array_map(
                fn (array $linea): array => $linea['tipo'] === 'combustible' && ! ($linea['tanque_lleno'] ?? false) ? $cambio($linea) : $linea,
                json_decode($copiada->lineas, true) ?: [],
            );

            DB::table('rutas_vuelta')->where('id', $copiada->id)->update(['lineas' => json_encode($lineas)]);
        }
    }
};
