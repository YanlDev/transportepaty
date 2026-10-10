<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Collection;

/**
 * Engancha una factura con las GR-transportista que cobra.
 *
 * Solo se asocia sola cuando no hay duda: la factura cita la GR por su número
 * y esa GR no tiene otra factura. Todo lo demás —la GR no está en el sistema,
 * está anulada, ya está facturada, o la factura cobra un período sin citar
 * GR— queda como alerta y la asocia una persona desde la bandeja «Facturas
 * por asociar». Equivocarse acá es equivocarse en la cobranza.
 */
class AsociadorFacturas
{
    /**
     * Asocia las GR que cita la factura y explica las que no pudo.
     *
     * @return array{asociadas: list<string>, alertas: list<string>}
     */
    public function asociarCitadas(Factura $factura): array
    {
        $asociadas = [];
        $alertas = [];
        $citadas = $factura->gr_citadas ?? [];

        $viajes = Viaje::query()
            ->conAnuladas()
            ->with('facturas:facturas.id,numero')
            ->whereIn('numero_gr', $citadas)
            ->get()
            ->keyBy('numero_gr');

        foreach ($citadas as $numeroGr) {
            $viaje = $viajes->get($numeroGr);

            if ($viaje === null) {
                $alertas[] = "La GR {$numeroGr} no está en el sistema: se asociará sola cuando la subas.";

                continue;
            }

            if ($viaje->facturas->contains('id', $factura->id)) {
                continue;
            }

            if ($viaje->anulada_at !== null) {
                $alertas[] = "La GR {$numeroGr} está anulada: no se asoció.";

                continue;
            }

            if ($viaje->esNoFacturable()) {
                $alertas[] = "La GR {$numeroGr} está marcada como «no se factura»: no se asoció.";

                continue;
            }

            if ($viaje->facturas->isNotEmpty()) {
                $otras = $viaje->facturas->pluck('numero')->join(', ');
                $alertas[] = "La GR {$numeroGr} ya tiene la factura {$otras}: asóciala a mano si también va en esta.";

                continue;
            }

            $factura->viajes()->attach($viaje->id);
            $asociadas[] = $numeroGr;
        }

        // Sin GR citadas solo hay que avisar si la factura no tiene ninguna:
        // una que ya se asoció a mano (y se vuelve a subir) está resuelta.
        if ($citadas === [] && ! $factura->viajes()->exists()) {
            $alertas[] = $factura->periodo_desde !== null && $factura->periodo_hasta !== null
                ? 'No cita GR: cobra el período del '.$factura->periodo_desde->format('d/m').' al '.$factura->periodo_hasta->format('d/m/Y').'. Confirma sus GR en «Facturas por asociar».'
                : 'No cita ninguna GR: asóciala a mano en «Facturas por asociar».';
        }

        return ['asociadas' => $asociadas, 'alertas' => $alertas];
    }

    /**
     * Cuando llega una GR que alguna factura citaba y no estaba en el sistema,
     * la asocia sola. Solo si la GR no tiene otra factura: si ya la tiene, la
     * decisión vuelve a ser de una persona.
     *
     * @return Collection<int, Factura> Las facturas a las que se asoció.
     */
    public function alLlegarViaje(Viaje $viaje): Collection
    {
        if ($viaje->anulada_at !== null || $viaje->esNoFacturable() || $viaje->facturas()->exists()) {
            return new Collection;
        }

        $facturas = Factura::query()
            ->whereJsonContains('gr_citadas', $viaje->numero_gr)
            ->get();

        foreach ($facturas as $factura) {
            $factura->viajes()->syncWithoutDetaching([$viaje->id]);
        }

        return $facturas;
    }

    /**
     * GR que una persona podría asociar a la factura: del mismo cliente (por
     * RUC), sin facturar y dentro del período que cobra la factura, o en los
     * dos meses previos a su emisión si no dice período. Las del período
     * vienen marcadas: es la propuesta que se confirma con un clic.
     *
     * @return array<int, array{id: int, numero_gr: string, fecha_traslado: string, destino: string|null, sugerida: bool}>
     */
    public function candidatas(Factura $factura): array
    {
        if ($factura->cliente_ruc === null) {
            return [];
        }

        $desde = $factura->periodo_desde ?? $factura->fecha_emision->copy()->subMonths(2);
        $hasta = $factura->periodo_hasta ?? $factura->fecha_emision;
        $porPeriodo = $factura->periodo_desde !== null && $factura->periodo_hasta !== null;

        return Viaje::query()
            ->where('cliente_ruc', $factura->cliente_ruc)
            ->whereDoesntHave('facturas')
            ->whereNull('no_facturable_at')
            ->whereBetween('fecha_traslado', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha_traslado')
            ->orderBy('numero_gr')
            ->limit(100)
            ->get(['id', 'numero_gr', 'fecha_traslado', 'destino'])
            ->map(fn (Viaje $viaje): array => [
                'id' => $viaje->id,
                'numero_gr' => $viaje->numero_gr,
                'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                'destino' => $viaje->ciudadDestino(),
                'sugerida' => $porPeriodo,
            ])
            ->values()
            ->all();
    }
}
