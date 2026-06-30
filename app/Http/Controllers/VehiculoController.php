<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\PosicionFoto;
use App\Enums\TipoCombustible;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreVehiculoRequest;
use App\Http\Requests\UpdateVehiculoRequest;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use App\Models\VehiculoFoto;
use App\Services\Combustible\RendimientoService;
use App\Services\Tracksolid\TracksolidClient;
use App\Services\Tracksolid\TracksolidDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehiculo::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'estado' => $request->string('estado')->value(),
            'sucursal_id' => $request->integer('sucursal_id') ?: null,
        ];

        $vehiculos = Vehiculo::query()
            ->visibleParaUsuario($request->user())
            ->select([
                'id', 'placa', 'marca', 'modelo', 'anio', 'tipo',
                'estado', 'kilometraje', 'sucursal_id', 'conductor_id',
            ])
            ->with([
                'sucursal:id,nombre',
                'conductor:id,nombres,apellidos',
                'fotoPrincipal.media',
            ])
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('placa', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('marca', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('modelo', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->when($filtros['estado'], fn ($query, string $estado) => $query->where('estado', $estado))
            ->when($filtros['sucursal_id'], fn ($query, int $sucursalId) => $query->where('sucursal_id', $sucursalId))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'anio' => $vehiculo->anio,
                'tipo' => $vehiculo->tipo->value,
                'estado' => $vehiculo->estado->value,
                'kilometraje' => $vehiculo->kilometraje,
                'sucursal_id' => $vehiculo->sucursal_id,
                'conductor_id' => $vehiculo->conductor_id,
                'sucursal' => $vehiculo->sucursal
                    ? ['id' => $vehiculo->sucursal->id, 'nombre' => $vehiculo->sucursal->nombre]
                    : null,
                'conductor' => $vehiculo->conductor
                    ? ['id' => $vehiculo->conductor->id, 'nombres' => $vehiculo->conductor->nombres, 'apellidos' => $vehiculo->conductor->apellidos]
                    : null,
                'foto' => $vehiculo->fotoPrincipal?->getFirstMediaUrl('imagen', 'thumb') ?: null,
            ]);

        return Inertia::render('vehiculos/index', [
            'vehiculos' => $vehiculos,
            'filtros' => $filtros,
            'sucursales' => $this->sucursales(),
            'estados' => EstadoVehiculo::options(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Vehiculo::class);

        return Inertia::render('vehiculos/create', $this->datosFormulario());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehiculoRequest $request): RedirectResponse
    {
        $this->authorize('create', Vehiculo::class);

        $vehiculo = Vehiculo::create($request->validated());

        return to_route('vehiculos.show', $vehiculo)
            ->with('toast', ['type' => 'success', 'message' => 'Vehículo registrado correctamente.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehiculo $vehiculo, RendimientoService $rendimiento): Response
    {
        $this->authorize('view', $vehiculo);

        $vehiculo->load([
            'sucursal:id,nombre,ciudad',
            'conductor:id,nombres,apellidos,telefono',
            'fotos.media',
        ]);

        $documentos = $vehiculo->documentos()
            ->with('media')
            ->take(3)
            ->get();

        return Inertia::render('vehiculos/show', [
            'rendimientoCombustible' => $this->rendimientoCombustible($vehiculo, $rendimiento),
            'puedeRegistrarCombustible' => request()->user()->can('registrar', [CargaCombustible::class, $vehiculo]),
            'vehiculo' => $vehiculo,
            'fotos' => $vehiculo->fotos->map(fn (VehiculoFoto $foto): array => [
                'id' => $foto->id,
                'posicion' => $foto->posicion->value,
                'posicion_label' => $foto->posicion->label(),
                'url' => $foto->getFirstMediaUrl('imagen'),
                'thumb' => $foto->getFirstMediaUrl('imagen', 'thumb'),
            ]),
            'documentos' => $documentos->map->toFrontArray(),
            'documentosTotal' => $vehiculo->documentos()->count(),
            'tiposDocumento' => TipoDocumento::options(),
            'posicionesFoto' => PosicionFoto::options(),
        ]);
    }

    /**
     * Chronological fuel-efficiency series (km/galón) for the show page chart,
     * excluding pending and anomalous loads.
     *
     * @return list<array{fecha: string, rendimiento: float}>
     */
    private function rendimientoCombustible(Vehiculo $vehiculo, RendimientoService $rendimiento): array
    {
        $procesadas = $vehiculo->cargasCombustible()
            ->whereNotNull('procesada_en')
            ->orderBy('fecha_carga')
            ->orderBy('id')
            ->get();

        $computado = collect($rendimiento->calcular($procesadas)['porCarga'])->keyBy('id');

        return $procesadas
            ->map(function (CargaCombustible $carga) use ($computado): ?array {
                $datos = $computado->get($carga->id);

                if ($datos === null || $datos['anomalia'] || $datos['rendimiento'] === null) {
                    return null;
                }

                return [
                    'fecha' => $carga->fecha_carga->format('d/m'),
                    'rendimiento' => $datos['rendimiento'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehiculo $vehiculo): Response
    {
        $this->authorize('update', $vehiculo);

        return Inertia::render('vehiculos/edit', [
            'vehiculo' => $vehiculo,
            ...$this->datosFormulario(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehiculoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $vehiculo->update($request->validated());

        return to_route('vehiculos.show', $vehiculo)
            ->with('toast', ['type' => 'success', 'message' => 'Vehículo actualizado correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('delete', $vehiculo);

        $vehiculo->delete();

        return to_route('vehiculos.index')
            ->with('toast', ['type' => 'success', 'message' => 'Vehículo eliminado correctamente.']);
    }

    /**
     * Shared data for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'sucursales' => $this->sucursales(),
            'conductores' => Conductor::query()
                ->where('activo', true)
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'sucursal_id'])
                ->map(fn (Conductor $conductor): array => [
                    'id' => $conductor->id,
                    'nombre_completo' => $conductor->nombre_completo,
                    'sucursal_id' => $conductor->sucursal_id,
                ]),
            'tipos' => TipoVehiculo::options(),
            'combustibles' => TipoCombustible::options(),
            'estados' => EstadoVehiculo::options(),
            'dispositivosGps' => $this->dispositivosGpsDisponibles(),
        ];
    }

    /**
     * IMEIs sugeridos para el campo GPS del formulario: dispositivos de la
     * cuenta Tracksolid que aún no están vinculados a otro vehículo.
     *
     * Se cachea brevemente y degrada a una lista vacía si la API falla, para
     * que el formulario nunca dependa de Tracksolid.
     *
     * @return array<int, array{imei: string, label: string}>
     */
    private function dispositivosGpsDisponibles(): array
    {
        if (! config('services.tracksolid.app_key')) {
            return [];
        }

        try {
            $dispositivos = Cache::remember(
                'tracksolid:device_list',
                now()->addSeconds(60),
                fn () => app(TracksolidClient::class)->listDevices()->all(),
            );
        } catch (\Throwable) {
            return [];
        }

        $imeisVinculados = Vehiculo::query()
            ->whereNotNull('imei')
            ->pluck('imei')
            ->all();

        return collect($dispositivos)
            ->map(fn (array $raw): TracksolidDevice => TracksolidDevice::fromArray($raw))
            ->reject(fn (TracksolidDevice $device): bool => in_array($device->imei(), $imeisVinculados, true))
            ->map(fn (TracksolidDevice $device): array => [
                'imei' => $device->imei(),
                'label' => trim(($device->modelo() ?? 'GPS').' · '.($device->placa() ?? $device->imei())),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, nombre: string}>
     */
    private function sucursales(): Collection
    {
        return Sucursal::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ]);
    }
}
