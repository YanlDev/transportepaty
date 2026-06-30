<?php

namespace App\Services\Tracksolid;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Construye el recorrido (ruta GPS) de un vehículo en un rango de fechas:
 * normaliza los puntos de `track.list` y calcula distancia, duración y
 * velocidades. Parte el rango en ventanas de ≤7 días (límite del API).
 */
class RecorridoService
{
    /** Minutos que se cachea cada ventana de recorrido. */
    private const CACHE_TTL = 10;

    public function __construct(private readonly TracksolidClient $client) {}

    /**
     * Recorrido entre dos instantes.
     *
     * @return array{
     *     puntos: list<array{lat: float, lng: float, hora: string|null, velocidad: int, rumbo: int}>,
     *     stats: array{distancia_km: float, duracion_min: int, velocidad_prom: int, velocidad_max: int, puntos: int, con_movimiento: bool}
     * }
     *
     * @throws TracksolidException
     */
    public function paraRango(string $imei, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $raw = [];
        $cursor = $desde;

        // El API permite máximo 7 días por consulta: troceamos el rango.
        while ($cursor < $hasta) {
            $fin = $cursor->addDays(7)->min($hasta);

            $clave = "tracksolid:track:{$imei}:{$cursor->format('YmdHis')}:{$fin->format('YmdHis')}";
            $chunk = Cache::remember(
                $clave,
                now()->addMinutes(self::CACHE_TTL),
                fn (): array => $this->client->trackList(
                    $imei,
                    $cursor->format('Y-m-d H:i:s'),
                    $fin->format('Y-m-d H:i:s'),
                )->all(),
            );

            $raw = array_merge($raw, $chunk);
            $cursor = $fin;
        }

        $puntos = [];

        foreach ($raw as $fila) {
            $lat = isset($fila['lat']) && is_numeric($fila['lat']) ? (float) $fila['lat'] : null;
            $lng = isset($fila['lng']) && is_numeric($fila['lng']) ? (float) $fila['lng'] : null;

            if ($lat === null || $lng === null || ($lat === 0.0 && $lng === 0.0)) {
                continue;
            }

            $puntos[] = [
                'lat' => $lat,
                'lng' => $lng,
                'hora' => isset($fila['gpsTime']) && is_string($fila['gpsTime']) ? $fila['gpsTime'] : null,
                'velocidad' => isset($fila['gpsSpeed']) && is_numeric($fila['gpsSpeed']) ? (int) round((float) $fila['gpsSpeed']) : 0,
                'rumbo' => isset($fila['direction']) && is_numeric($fila['direction']) ? (int) $fila['direction'] : 0,
            ];
        }

        return ['puntos' => $puntos, 'stats' => $this->stats($puntos)];
    }

    /**
     * @param  list<array{lat: float, lng: float, hora: string|null, velocidad: int, rumbo: int}>  $puntos
     * @return array{distancia_km: float, duracion_min: int, velocidad_prom: int, velocidad_max: int, puntos: int, con_movimiento: bool}
     */
    private function stats(array $puntos): array
    {
        $distancia = 0.0;
        $velocidadMax = 0;
        $velocidades = [];
        $tiempoMovSeg = 0; // tiempo con el vehículo en movimiento

        foreach ($puntos as $i => $punto) {
            $velocidadMax = max($velocidadMax, $punto['velocidad']);

            if ($punto['velocidad'] > 0) {
                $velocidades[] = $punto['velocidad'];
            }

            if ($i > 0) {
                $anterior = $puntos[$i - 1];
                $distancia += $this->haversineKm($anterior, $punto);

                // Sólo cuenta como "duración" el tiempo en movimiento; se acota
                // cada tramo a 30 min para no sumar pausas con reporte esporádico.
                if ($punto['velocidad'] > 0 && $punto['hora'] !== null && $anterior['hora'] !== null) {
                    $delta = strtotime($punto['hora']) - strtotime($anterior['hora']);

                    if ($delta > 0 && $delta <= 1800) {
                        $tiempoMovSeg += $delta;
                    }
                }
            }
        }

        return [
            'distancia_km' => round($distancia, 1),
            'duracion_min' => (int) round($tiempoMovSeg / 60),
            'velocidad_prom' => $velocidades !== [] ? (int) round(array_sum($velocidades) / count($velocidades)) : 0,
            'velocidad_max' => $velocidadMax,
            'puntos' => count($puntos),
            'con_movimiento' => $distancia >= 0.1,
        ];
    }

    /**
     * Distancia en kilómetros entre dos puntos (fórmula de Haversine).
     *
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    private function haversineKm(array $a, array $b): float
    {
        $radio = 6371.0;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return $radio * 2 * asin(min(1.0, sqrt($h)));
    }
}
