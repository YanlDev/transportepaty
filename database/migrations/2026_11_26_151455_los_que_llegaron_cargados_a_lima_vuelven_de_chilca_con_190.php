<?php

use App\Models\Ruta;
use App\Models\RutaVuelta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los 180 gal de «Particular de Chilca a Juliaca» son para la unidad que
     * baja de Pisco a Chilca sin llegar a Lima. La que llegó cargada a Lima
     * (cal a Cajamarquilla) y de ahí bajó a Chilca hace el recorrido entero:
     * le toca «Particular a Juliaca» (190).
     *
     * Corrige los tickets que quedaron con la de Chilca, salvo los que ya
     * recibieron combustible o viáticos en la vuelta: lo autorizado se midió
     * contra esa ruta.
     */
    public function up(): void
    {
        $chilca = Ruta::query()->where('nombre', 'Particular de Chilca a Juliaca')->first();
        $completa = Ruta::query()->where('nombre', 'Particular a Juliaca')->with(['lineas.punto', 'variantes'])->first();

        if ($chilca === null || $completa === null) {
            return;
        }

        $porCorregir = RutaVuelta::query()
            ->where('ruta_id', $chilca->id)
            ->where('sentido', 'regreso')
            ->whereExists(fn ($paradas) => $paradas
                ->from('paradas_vuelta')
                ->whereColumn('paradas_vuelta.vuelta_id', 'rutas_vuelta.vuelta_id')
                ->where('paradas_vuelta.sentido', 'ida')
                ->where('paradas_vuelta.zona_destino', 'lima')
                ->where('paradas_vuelta.tipo_carga', '!=', 'vacio'))
            ->whereNotExists(fn ($abastecimientos) => $abastecimientos
                ->from('abastecimientos')
                ->whereColumn('abastecimientos.vuelta_id', 'rutas_vuelta.vuelta_id')
                ->where('abastecimientos.sentido', 'regreso')
                ->whereNull('abastecimientos.anulado_at'))
            ->whereNotExists(fn ($viaticos) => $viaticos
                ->from('viaticos')
                ->join('lineas_ruta', 'lineas_ruta.id', '=', 'viaticos.linea_ruta_id')
                ->whereColumn('viaticos.vuelta_id', 'rutas_vuelta.vuelta_id')
                ->where('lineas_ruta.ruta_id', $chilca->id)
                ->whereNull('viaticos.anulado_at'))
            ->pluck('id');

        if ($porCorregir->isEmpty()) {
            return;
        }

        $copia = RutaVuelta::copiaDe($completa, null);

        DB::table('rutas_vuelta')->whereIn('id', $porCorregir)->update([
            ...$copia,
            'lineas' => json_encode($copia['lineas']),
            'updated_at' => now(),
        ]);
    }

    /**
     * No se deshace: no queda dicho qué tickets tenían la ruta de Chilca.
     */
    public function down(): void {}
};
