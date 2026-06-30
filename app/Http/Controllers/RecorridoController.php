<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use App\Services\Tracksolid\RecorridoService;
use App\Services\Tracksolid\TracksolidException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecorridoController extends Controller
{
    /**
     * Recorrido (ruta GPS) de un vehículo en un rango. Se consume vía fetch
     * desde el mapa de flota.
     */
    public function index(Request $request, Vehiculo $vehiculo, RecorridoService $recorridos): JsonResponse
    {
        $this->authorize('view', $vehiculo);

        if (! $vehiculo->tieneGps()) {
            return response()->json(['error' => 'Este vehículo no tiene un dispositivo GPS vinculado.'], 422);
        }

        [$desde, $hasta] = $this->rango($request);

        try {
            $recorrido = $recorridos->paraRango((string) $vehiculo->imei, $desde, $hasta);
        } catch (TracksolidException $e) {
            return response()->json(['error' => "No se pudo obtener el recorrido: {$e->getMessage()}"], 502);
        }

        return response()->json([
            ...$recorrido,
            'rango' => [
                'desde' => $desde->format('Y-m-d H:i'),
                'hasta' => $hasta->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Resuelve el rango [desde, hasta] desde el preset (o fechas personalizadas).
     * Limitado a los últimos 3 meses (límite del API).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rango(Request $request): array
    {
        $ahora = CarbonImmutable::now();
        $minimo = $ahora->subMonths(3);

        [$desde, $hasta] = match ($request->string('preset')->value()) {
            'ayer' => [$ahora->subDay()->startOfDay(), $ahora->subDay()->endOfDay()],
            '3dias' => [$ahora->subDays(2)->startOfDay(), $ahora],
            'semana' => [$ahora->startOfWeek(), $ahora],
            'semana_pasada' => [$ahora->subWeek()->startOfWeek(), $ahora->subWeek()->endOfWeek()],
            'mes' => [$ahora->startOfMonth(), $ahora],
            'mes_pasado' => [$ahora->subMonth()->startOfMonth(), $ahora->subMonth()->endOfMonth()],
            'personalizado' => [
                CarbonImmutable::parse($request->date('desde') ?? $ahora)->startOfDay(),
                CarbonImmutable::parse($request->date('hasta') ?? $request->date('desde') ?? $ahora)->endOfDay(),
            ],
            default => [$ahora->startOfDay(), $ahora], // hoy
        };

        return [$desde->max($minimo), $hasta];
    }
}
