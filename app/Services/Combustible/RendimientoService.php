<?php

namespace App\Services\Combustible;

use App\Models\CargaCombustible;
use Illuminate\Support\Collection;

/**
 * Computes fuel efficiency from fuel loads using the fill-up method: the
 * kilometres travelled between two consecutive (chronological) loads divided
 * by the gallons of the later load (km/galón). GPS is intentionally NOT used:
 * the dashboard photo is the source of truth at the moment of refuelling.
 */
class RendimientoService
{
    /** Plausible km/galón band; outside it a load is flagged as an anomaly. */
    private const RENDIMIENTO_MIN = 2.0;

    private const RENDIMIENTO_MAX = 100.0;

    /**
     * Enriches each processed load with its travelled km, efficiency and an
     * anomaly flag, and produces a period summary. Loads may come in any
     * order; they are processed chronologically by `fecha_carga` then `id`.
     *
     * @param  Collection<int, CargaCombustible>  $cargas  processed loads only
     * @return array{
     *     porCarga: array<int, array{
     *         id: int,
     *         km_recorridos: int|null,
     *         rendimiento: float|null,
     *         anomalia: bool,
     *         motivo_anomalia: string|null
     *     }>,
     *     resumen: array{
     *         total_cargas: int,
     *         total_galones: float,
     *         total_costo: float,
     *         km_total: int,
     *         rendimiento_promedio: float|null,
     *         rendimiento_ultimo: float|null,
     *         costo_por_km: float|null
     *     }
     * }
     */
    public function calcular(Collection $cargas): array
    {
        $ordenadas = $cargas
            ->sort(fn (CargaCombustible $a, CargaCombustible $b): int => [$a->fecha_carga->getTimestamp(), $a->id] <=> [$b->fecha_carga->getTimestamp(), $b->id])
            ->values();

        $porCarga = [];
        $odometroAnterior = null;

        $kmValidos = 0;
        $galonesValidos = 0.0;
        $totalGalones = 0.0;
        $totalCosto = 0.0;
        $rendimientoUltimo = null;

        foreach ($ordenadas as $carga) {
            $odometro = (int) $carga->odometro;
            $galones = (float) $carga->galones;

            $totalGalones += $galones;
            $totalCosto += (float) $carga->costo_total;

            [$km, $rendimiento, $anomalia, $motivo] = $this->evaluar(
                $odometro,
                $galones,
                $odometroAnterior,
            );

            if ($km !== null && ! $anomalia) {
                $kmValidos += $km;
                $galonesValidos += $galones;
                $rendimientoUltimo = $rendimiento;
            }

            $porCarga[] = [
                'id' => (int) $carga->id,
                'km_recorridos' => $km,
                'rendimiento' => $rendimiento,
                'anomalia' => $anomalia,
                'motivo_anomalia' => $motivo,
            ];

            $odometroAnterior = $odometro;
        }

        $rendimientoPromedio = $galonesValidos > 0
            ? round($kmValidos / $galonesValidos, 2)
            : null;

        $costoPorKm = $kmValidos > 0
            ? round($totalCosto / $kmValidos, 2)
            : null;

        return [
            'porCarga' => $porCarga,
            'resumen' => [
                'total_cargas' => $ordenadas->count(),
                'total_galones' => round($totalGalones, 3),
                'total_costo' => round($totalCosto, 2),
                'km_total' => $kmValidos,
                'rendimiento_promedio' => $rendimientoPromedio,
                'rendimiento_ultimo' => $rendimientoUltimo,
                'costo_por_km' => $costoPorKm,
            ],
        ];
    }

    /**
     * Evaluates a single load against the previous odometer reading.
     *
     * @return array{0: int|null, 1: float|null, 2: bool, 3: string|null}
     *                                                                    [km_recorridos, rendimiento, anomalia, motivo_anomalia]
     */
    private function evaluar(int $odometro, float $galones, ?int $odometroAnterior): array
    {
        // Primera carga del vehículo: línea base, no hay con qué comparar.
        if ($odometroAnterior === null) {
            return [null, null, false, null];
        }

        if ($galones <= 0) {
            return [null, null, true, 'La carga no tiene galones registrados.'];
        }

        $km = $odometro - $odometroAnterior;

        // El odómetro no avanzó: typo, tablero reseteado o registro fuera de orden.
        if ($km <= 0) {
            return [null, null, true, 'El odómetro no avanzó respecto a la carga anterior.'];
        }

        $rendimiento = round($km / $galones, 2);

        // Rendimiento fuera de rango: probable error en odómetro o galones.
        if ($rendimiento < self::RENDIMIENTO_MIN || $rendimiento > self::RENDIMIENTO_MAX) {
            return [$km, $rendimiento, true, 'Rendimiento fuera del rango esperado; revisa el odómetro o los galones.'];
        }

        return [$km, $rendimiento, false, null];
    }
}
