<?php

namespace App\Http\Controllers;

use App\Enums\TipoCombustible;
use App\Http\Requests\StoreCargaCombustibleRequest;
use App\Http\Requests\UpdateCargaCombustibleRequest;
use App\Models\CargaCombustible;
use App\Models\Vehiculo;
use App\Services\Combustible\RendimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargaCombustibleController extends Controller
{
    public function __construct(private RendimientoService $rendimiento) {}

    /**
     * Quick-register entry point for drivers: lists the vehicles assigned to
     * the authenticated user so they can register a load on one of them.
     */
    public function rapido(Request $request): Response
    {
        $vehiculos = Vehiculo::query()
            ->whereHas('conductor', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('fotoPrincipal.media')
            ->orderBy('placa')
            ->get();

        return Inertia::render('combustible/rapido', [
            'vehiculos' => $vehiculos->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'foto' => $vehiculo->fotoPrincipal?->getFirstMediaUrl('imagen', 'thumb') ?: null,
            ])->values(),
        ]);
    }

    /**
     * Bandeja de cargas por procesar (toda la flota), para el admin.
     */
    public function pendientes(Request $request): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $cargas = CargaCombustible::query()
            ->whereNull('procesada_en')
            ->with(['vehiculo:id,placa,marca,modelo,kilometraje', 'media', 'registradaPor:id,name'])
            ->orderBy('fecha_carga')
            ->get();

        return Inertia::render('combustible/pendientes', [
            'cargas' => $cargas->map(fn (CargaCombustible $carga): array => [
                ...$this->aDto($carga, null),
                'vehiculo' => [
                    'id' => $carga->vehiculo->id,
                    'placa' => $carga->vehiculo->placa,
                    'marca' => $carga->vehiculo->marca,
                    'modelo' => $carga->vehiculo->modelo,
                ],
                'odometro_sugerido' => $carga->vehiculo->kilometraje,
            ])->values(),
        ]);
    }

    public function index(Vehiculo $vehiculo): Response
    {
        $this->authorize('verHistorial', [CargaCombustible::class, $vehiculo]);

        $cargas = $vehiculo->cargasCombustible()
            ->with(['media', 'registradaPor:id,name'])
            ->orderByDesc('fecha_carga')
            ->orderByDesc('id')
            ->get();

        $procesadas = $cargas->filter(fn (CargaCombustible $carga): bool => $carga->procesada);

        $calculo = $this->rendimiento->calcular($procesadas);
        $computado = collect($calculo['porCarga'])->keyBy('id');

        return Inertia::render('vehiculos/combustible', [
            'vehiculo' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'kilometraje' => $vehiculo->kilometraje,
                'es_electrico' => $vehiculo->combustible === TipoCombustible::Electrico,
            ],
            'cargas' => $cargas->map(fn (CargaCombustible $carga): array => $this->aDto(
                $carga,
                $computado->get($carga->id),
            ))->values(),
            'resumen' => $calculo['resumen'],
            'odometroSugerido' => $procesadas->max('odometro') ?: $vehiculo->kilometraje,
            'puede' => [
                'registrar' => $this->can('registrar', [CargaCombustible::class, $vehiculo]),
                'gestionar' => $this->can('update', $this->cargaPlantilla($vehiculo)),
            ],
        ]);
    }

    public function store(StoreCargaCombustibleRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('registrar', [CargaCombustible::class, $vehiculo]);

        $galones = $request->validated('galones');
        $directo = $galones !== null;

        $datos = [
            'registrada_por' => $request->user()->id,
            'fecha_carga' => $request->date('fecha_carga') ?? now(),
        ];

        if ($directo) {
            $costoTotal = $request->validated('costo_total');

            $datos += [
                'odometro' => $request->validated('odometro'),
                'galones' => $galones,
                'costo_total' => $costoTotal,
                'precio_por_galon' => $costoTotal !== null ? round((float) $costoTotal / (float) $galones, 3) : null,
                'procesada_por' => $request->user()->id,
                'procesada_en' => now(),
            ];
        }

        $carga = $vehiculo->cargasCombustible()->create($datos);

        if ($request->hasFile('comprobante')) {
            $carga->addMediaFromRequest('comprobante')->toMediaCollection('comprobante');
        }

        if ($request->hasFile('odometro_foto')) {
            $carga->addMediaFromRequest('odometro_foto')->toMediaCollection('odometro_foto');
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $directo
                ? 'Carga registrada correctamente.'
                : 'Carga registrada. Un administrador completará los datos.',
        ]);
    }

    public function update(UpdateCargaCombustibleRequest $request, Vehiculo $vehiculo, CargaCombustible $carga): RedirectResponse
    {
        abort_unless($carga->vehiculo_id === $vehiculo->id, 404);

        $this->authorize('update', $carga);

        $galones = (float) $request->validated('galones');
        $costoTotal = $request->validated('costo_total');

        $carga->update([
            'fecha_carga' => $request->date('fecha_carga'),
            'odometro' => $request->validated('odometro'),
            'galones' => $galones,
            'costo_total' => $costoTotal,
            'precio_por_galon' => $costoTotal !== null ? round((float) $costoTotal / $galones, 3) : null,
            'observaciones' => $request->validated('observaciones'),
            'procesada_por' => $request->user()->id,
            'procesada_en' => $carga->procesada_en ?? now(),
        ]);

        if ($request->hasFile('comprobante')) {
            $carga->addMediaFromRequest('comprobante')->toMediaCollection('comprobante');
        }

        if ($request->hasFile('odometro_foto')) {
            $carga->addMediaFromRequest('odometro_foto')->toMediaCollection('odometro_foto');
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Carga procesada correctamente.',
        ]);
    }

    public function destroy(Vehiculo $vehiculo, CargaCombustible $carga): RedirectResponse
    {
        abort_unless($carga->vehiculo_id === $vehiculo->id, 404);

        $this->authorize('delete', $carga);

        $carga->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Carga eliminada correctamente.',
        ]);
    }

    /**
     * Builds the frontend DTO for a load, merging the computed efficiency
     * fields (null for pending or anomalous loads).
     *
     * @param  array{km_recorridos: int|null, rendimiento: float|null, anomalia: bool, motivo_anomalia: string|null}|null  $computado
     * @return array<string, mixed>
     */
    private function aDto(CargaCombustible $carga, ?array $computado): array
    {
        return [
            'id' => $carga->id,
            'fecha_carga' => $carga->fecha_carga->toIso8601String(),
            'odometro' => $carga->odometro,
            'galones' => $carga->galones !== null ? (float) $carga->galones : null,
            'costo_total' => $carga->costo_total !== null ? (float) $carga->costo_total : null,
            'precio_por_galon' => $carga->precio_por_galon !== null ? (float) $carga->precio_por_galon : null,
            'observaciones' => $carga->observaciones,
            'procesada' => $carga->procesada,
            'registrada_por' => $carga->registradaPor?->name,
            'comprobante_url' => $carga->getFirstMediaUrl('comprobante') ?: null,
            'comprobante_thumb' => $carga->getFirstMediaUrl('comprobante', 'thumb') ?: null,
            'odometro_foto_url' => $carga->getFirstMediaUrl('odometro_foto') ?: null,
            'odometro_foto_thumb' => $carga->getFirstMediaUrl('odometro_foto', 'thumb') ?: null,
            'km_recorridos' => $computado['km_recorridos'] ?? null,
            'rendimiento' => $computado['rendimiento'] ?? null,
            'anomalia' => $computado['anomalia'] ?? false,
            'motivo_anomalia' => $computado['motivo_anomalia'] ?? null,
        ];
    }

    /**
     * A throwaway load bound to the vehicle, used only to evaluate the
     * gestionar (update) ability without a persisted row.
     */
    private function cargaPlantilla(Vehiculo $vehiculo): CargaCombustible
    {
        return (new CargaCombustible)->forceFill(['vehiculo_id' => $vehiculo->id]);
    }

    private function can(string $ability, mixed $arguments): bool
    {
        return request()->user()?->can($ability, $arguments) ?? false;
    }
}
