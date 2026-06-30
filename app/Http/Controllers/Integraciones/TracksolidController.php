<?php

namespace App\Http\Controllers\Integraciones;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use App\Services\Tracksolid\TracksolidClient;
use App\Services\Tracksolid\TracksolidDevice;
use App\Services\Tracksolid\TracksolidException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TracksolidController extends Controller
{
    /**
     * Pantalla de administración de dispositivos GPS / Tracksolid.
     */
    public function index(): Response
    {
        Gate::authorize('gestionar-gps');

        $vehiculosPorImei = Vehiculo::query()
            ->whereNotNull('imei')
            ->get(['id', 'placa', 'marca', 'modelo', 'imei'])
            ->keyBy('imei');

        $placasRegistradas = Vehiculo::query()->pluck('id', 'placa');

        $error = null;
        $dispositivos = [];

        try {
            $dispositivos = app(TracksolidClient::class)
                ->listDevices()
                ->map(function (array $raw) use ($vehiculosPorImei, $placasRegistradas): array {
                    $device = TracksolidDevice::fromArray($raw);
                    $vehiculo = $vehiculosPorImei->get($device->imei());

                    return [
                        ...$device->toFrontArray(),
                        'vehiculo' => $vehiculo
                            ? ['id' => $vehiculo->id, 'placa' => $vehiculo->placa, 'marca' => $vehiculo->marca, 'modelo' => $vehiculo->modelo]
                            : null,
                        'vehiculo_sugerido_id' => $device->placa() ? $placasRegistradas->get($device->placa()) : null,
                    ];
                })
                ->values()
                ->all();
        } catch (TracksolidException $e) {
            $error = "No se pudo consultar Tracksolid [{$e->apiCode}]: {$e->getMessage()}";
        }

        return Inertia::render('integraciones/tracksolid', [
            'dispositivos' => $dispositivos,
            'error' => $error,
            'vehiculosDisponibles' => Vehiculo::query()
                ->whereNull('imei')
                ->orderBy('placa')
                ->get(['id', 'placa', 'marca', 'modelo'])
                ->map(fn (Vehiculo $v): array => [
                    'id' => $v->id,
                    'placa' => $v->placa,
                    'descripcion' => "{$v->placa} · {$v->marca} {$v->modelo}",
                ]),
            'sucursales' => Sucursal::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (Sucursal $s): array => ['id' => $s->id, 'nombre' => $s->nombre]),
        ]);
    }

    /**
     * Vincula un dispositivo (IMEI) a un vehículo existente.
     */
    public function vincular(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-gps');

        $validated = $request->validate([
            'imei' => ['required', 'string', 'max:50'],
            'vehiculo_id' => ['required', 'integer', 'exists:vehiculos,id'],
        ]);

        if (Vehiculo::query()->where('imei', $validated['imei'])->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Ese dispositivo ya está vinculado a otro vehículo.',
            ]);
        }

        $vehiculo = Vehiculo::query()->where('id', $validated['vehiculo_id'])->firstOrFail();
        $vehiculo->update(['imei' => $validated['imei']]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Dispositivo vinculado a {$vehiculo->placa}.",
        ]);
    }

    /**
     * Importa un dispositivo como un vehículo nuevo.
     */
    public function importar(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-gps');

        $validated = $request->validate([
            'imei' => ['required', 'string', 'max:50'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
        ]);

        if (Vehiculo::query()->where('imei', $validated['imei'])->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Ese dispositivo ya está vinculado a un vehículo.',
            ]);
        }

        try {
            $device = TracksolidDevice::fromArray(
                app(TracksolidClient::class)->deviceDetail($validated['imei'])
            );
        } catch (TracksolidException $e) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "No se pudo obtener el detalle del dispositivo: {$e->getMessage()}",
            ]);
        }

        $vehiculo = Vehiculo::create([
            'sucursal_id' => $validated['sucursal_id'],
            'imei' => $validated['imei'],
            'placa' => $device->placa() ?? 'GPS-'.substr($device->imei(), -6),
            'marca' => $device->marca() ?? 'Por definir',
            'modelo' => $device->modeloVehiculo() ?? 'Por definir',
            'anio' => (int) date('Y'),
            'numero_serie' => $device->vin(),
            'numero_motor' => $device->numeroMotor(),
            // Arranca con la lectura del GPS como base (offset 0); el usuario
            // luego calibra al odómetro real del tablero.
            'kilometraje' => $device->kilometraje() ?? 0,
            'gps_km_base' => $device->kilometraje(),
        ]);

        return to_route('vehiculos.show', $vehiculo)->with('toast', [
            'type' => 'success',
            'message' => "Vehículo {$vehiculo->placa} importado desde GPS. Completa sus datos.",
        ]);
    }

    /**
     * Sincroniza los datos del vehículo desde su dispositivo GPS.
     */
    public function sincronizar(Vehiculo $vehiculo): RedirectResponse
    {
        Gate::authorize('gestionar-gps');

        if (! $vehiculo->tieneGps()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Este vehículo no tiene un dispositivo GPS vinculado.',
            ]);
        }

        try {
            $device = TracksolidDevice::fromArray(
                app(TracksolidClient::class)->deviceDetail((string) $vehiculo->imei)
            );
        } catch (TracksolidException $e) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "No se pudo sincronizar con el GPS: {$e->getMessage()}",
            ]);
        }

        $vehiculo->update($device->toVehiculoAttributes());

        $lecturaGps = $device->kilometraje();

        if ($lecturaGps !== null) {
            $vehiculo->aplicarLecturaGps($lecturaGps);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Datos sincronizados desde el GPS.',
        ]);
    }

    /**
     * Calibra el odómetro real del vehículo contra la lectura actual del GPS.
     */
    public function calibrar(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        Gate::authorize('gestionar-gps');

        $validated = $request->validate([
            'kilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
        ]);

        $lecturaGps = null;

        if ($vehiculo->tieneGps()) {
            try {
                $device = TracksolidDevice::fromArray(
                    app(TracksolidClient::class)->deviceDetail((string) $vehiculo->imei)
                );
                $lecturaGps = $device->kilometraje();
            } catch (TracksolidException) {
                // Sin lectura del GPS calibramos sólo el valor real; la base se
                // fijará en la próxima sincronización.
            }
        }

        $vehiculo->calibrarOdometro($validated['kilometraje'], $lecturaGps);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Odómetro calibrado correctamente.',
        ]);
    }

    /**
     * Pantalla de cámaras en vivo de un vehículo (dashcam).
     */
    public function camaraPage(Vehiculo $vehiculo): Response
    {
        Gate::authorize('view', $vehiculo);

        $urlInicial = null;

        if ($vehiculo->tieneGps()) {
            try {
                $urlInicial = app(TracksolidClient::class)->liveVideoUrl((string) $vehiculo->imei, 1);
            } catch (TracksolidException) {
                // El frontend mostrará el error al reintentar.
            }
        }

        return Inertia::render('vehiculos/camara', [
            'vehiculo' => $vehiculo->only(['id', 'placa', 'marca', 'modelo']),
            'urlInicial' => $urlInicial,
            // El selector lista solo los vehículos visibles para el usuario:
            // el conductor ve los suyos; admin y visor, toda la flota con GPS.
            'vehiculosGps' => Vehiculo::query()
                ->visibleParaUsuario(request()->user())
                ->whereNotNull('imei')
                ->orderBy('placa')
                ->get(['id', 'placa'])
                ->map(fn (Vehiculo $v): array => ['id' => $v->id, 'placa' => $v->placa]),
        ]);
    }

    /**
     * URL de video en vivo de la dashcam para un canal (1 = carretera,
     * 2 = cabina). Se consume vía fetch desde la pantalla de cámaras.
     */
    public function camara(Request $request, Vehiculo $vehiculo): JsonResponse
    {
        Gate::authorize('view', $vehiculo);

        if (! $vehiculo->tieneGps()) {
            return response()->json(['error' => 'Este vehículo no tiene un dispositivo GPS vinculado.'], 422);
        }

        $client = app(TracksolidClient::class);
        $imei = (string) $vehiculo->imei;

        try {
            if ($request->string('modo')->value() === 'historico') {
                $url = $client->historicVideoUrl($imei);
            } else {
                $channel = $request->integer('channel');
                $channel = in_array($channel, [1, 2], true) ? $channel : 1;
                $url = $client->liveVideoUrl($imei, $channel);
            }
        } catch (TracksolidException $e) {
            return response()->json(['error' => "No se pudo abrir la cámara: {$e->getMessage()}"], 502);
        }

        if ($url === null) {
            return response()->json(['error' => 'La cámara no está disponible (dispositivo sin cámara u offline).'], 422);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * Desvincula el dispositivo GPS de un vehículo.
     */
    public function desvincular(Vehiculo $vehiculo): RedirectResponse
    {
        Gate::authorize('gestionar-gps');

        $vehiculo->update(['imei' => null]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Dispositivo GPS desvinculado.',
        ]);
    }
}
