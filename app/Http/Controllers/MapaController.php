<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use App\Services\Tracksolid\TracksolidException;
use App\Services\Tracksolid\UbicacionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapaController extends Controller
{
    /**
     * Mapa de la flota con la última posición de cada vehículo con GPS.
     */
    public function index(Request $request, UbicacionService $ubicaciones): Response
    {
        $this->authorize('viewAny', Vehiculo::class);

        $vehiculos = Vehiculo::query()
            ->visibleParaUsuario($request->user())
            ->whereNotNull('imei')
            ->with('sucursal:id,nombre')
            ->get(['id', 'placa', 'marca', 'modelo', 'estado', 'imei', 'sucursal_id']);

        $error = null;
        $marcadores = [];

        $imeis = array_values(
            $vehiculos->map(fn (Vehiculo $vehiculo): string => (string) $vehiculo->imei)->all()
        );

        try {
            $posiciones = $ubicaciones->obtener($imeis);

            $marcadores = $vehiculos
                ->map(function (Vehiculo $vehiculo) use ($posiciones): ?array {
                    $ubicacion = $posiciones[$vehiculo->imei] ?? null;

                    if ($ubicacion === null || ! $ubicacion->tienePosicion()) {
                        return null;
                    }

                    return [
                        'id' => $vehiculo->id,
                        'placa' => $vehiculo->placa,
                        'marca' => $vehiculo->marca,
                        'modelo' => $vehiculo->modelo,
                        'estado_vehiculo' => $vehiculo->estado->value,
                        'sucursal' => $vehiculo->sucursal?->nombre,
                        ...$ubicacion->toFrontArray(),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } catch (TracksolidException $e) {
            $error = "No se pudieron obtener las ubicaciones [{$e->apiCode}]: {$e->getMessage()}";
        }

        return Inertia::render('mapa/index', [
            'marcadores' => $marcadores,
            'totalConGps' => $vehiculos->count(),
            'error' => $error,
            'focusId' => $request->integer('vehiculo') ?: null,
            'recorridoId' => $request->integer('recorrido') ?: null,
            'vehiculosGps' => Vehiculo::query()
                ->visibleParaUsuario($request->user())
                ->whereNotNull('imei')
                ->orderBy('placa')
                ->get(['id', 'placa'])
                ->map(fn (Vehiculo $v): array => ['id' => $v->id, 'placa' => $v->placa]),
        ]);
    }
}
