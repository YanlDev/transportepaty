<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMantenimientoRequest;
use App\Http\Requests\UpdateMantenimientoRequest;
use App\Models\Mantenimiento;
use App\Models\Vehiculo;
use App\Services\Mantenimiento\PlanMantenimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MantenimientoController extends Controller
{
    public function __construct(
        private PlanMantenimientoService $plan,
    ) {}

    public function index(Request $request, Vehiculo $vehiculo): Response
    {
        $this->authorize('verHistorial', [Mantenimiento::class, $vehiculo]);

        $user = $request->user();

        $mantenimientos = $vehiculo->mantenimientos()
            ->with(['items', 'registradoPor:id,name', 'media'])
            ->orderByDesc('fecha_realizado')
            ->orderByDesc('id')
            ->get();

        $proximos = $this->plan->proximosVencimientos($vehiculo);

        return Inertia::render('vehiculos/mantenimiento', [
            'vehiculo' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'tipo' => $vehiculo->tipo->value,
                'kilometraje' => $vehiculo->kilometraje,
                'odometro_vigente' => $this->plan->odometroVigente($vehiculo),
                'tiene_gps' => $vehiculo->tieneGps(),
            ],
            'plan' => array_map(fn (array $p): array => $this->planItemDto($p), $proximos),
            'plantillas' => $this->plan->plantillasParaVehiculo($vehiculo)
                ->map(fn ($plantilla): array => [
                    'id' => $plantilla->id,
                    'nombre' => $plantilla->nombre,
                    'tipo_mantenimiento' => $plantilla->tipo_mantenimiento,
                ])->values(),
            'estado_general' => $this->plan->conteoEstados($proximos),
            'costos_anio' => $this->plan->costosAnio($vehiculo, now()->year),
            'mantenimientos' => $mantenimientos->map(fn (Mantenimiento $m): array => $this->aDto($m))->values(),
            'estadisticas' => $this->plan->estadisticas($vehiculo),
            'odometro_minimo' => $this->plan->odometroMinimo($vehiculo),
            'puede' => [
                'registrar' => $user->can('registrar', [Mantenimiento::class, $vehiculo]),
                'gestionar' => $user->can('update', $this->mantenimientoPlantilla($vehiculo)),
            ],
        ]);
    }

    public function store(StoreMantenimientoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('registrar', [Mantenimiento::class, $vehiculo]);

        $data = $request->validated();

        $costoTotal = $data['costo_total'] ?? collect($data['items'])->sum('costo');

        $mantenimiento = $vehiculo->mantenimientos()->create([
            'registrado_por' => $request->user()->id,
            'conductor_id' => $data['conductor_id'] ?? null,
            'fecha_realizado' => $data['fecha_realizado'],
            'odometro' => $data['odometro'],
            'proveedor' => $data['proveedor'] ?? null,
            'factura_numero' => $data['factura_numero'] ?? null,
            'costo_total' => $costoTotal ?: null,
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        foreach ($data['items'] as $itemData) {
            $mantenimiento->items()->create($itemData);
        }

        if ($request->hasFile('comprobante')) {
            $mantenimiento->addMediaFromRequest('comprobante')->toMediaCollection('comprobante');
        }

        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $mantenimiento->addMedia($foto)->toMediaCollection('fotos');
            }
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Mantenimiento registrado correctamente.',
        ]);
    }

    public function update(UpdateMantenimientoRequest $request, Vehiculo $vehiculo, Mantenimiento $mantenimiento): RedirectResponse
    {
        abort_unless($mantenimiento->vehiculo_id === $vehiculo->id, 404);

        $this->authorize('update', $mantenimiento);

        $data = $request->validated();

        $mantenimiento->update([
            'fecha_realizado' => $data['fecha_realizado'] ?? $mantenimiento->fecha_realizado,
            'odometro' => $data['odometro'] ?? $mantenimiento->odometro,
            'proveedor' => $data['proveedor'] ?? $mantenimiento->proveedor,
            'factura_numero' => $data['factura_numero'] ?? $mantenimiento->factura_numero,
            'costo_total' => array_key_exists('costo_total', $data) ? $data['costo_total'] : $mantenimiento->costo_total,
            'observaciones' => $data['observaciones'] ?? $mantenimiento->observaciones,
        ]);

        if (isset($data['items'])) {
            $mantenimiento->items()->delete();
            foreach ($data['items'] as $itemData) {
                $mantenimiento->items()->create($itemData);
            }

            if (! array_key_exists('costo_total', $data) || $data['costo_total'] === null) {
                $mantenimiento->updateQuietly([
                    'costo_total' => collect($data['items'])->sum('costo') ?: null,
                ]);
            }
        }

        if ($request->hasFile('comprobante')) {
            $mantenimiento->addMediaFromRequest('comprobante')->toMediaCollection('comprobante');
        }

        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $mantenimiento->addMedia($foto)->toMediaCollection('fotos');
            }
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Mantenimiento actualizado correctamente.',
        ]);
    }

    public function destroy(Vehiculo $vehiculo, Mantenimiento $mantenimiento): RedirectResponse
    {
        abort_unless($mantenimiento->vehiculo_id === $vehiculo->id, 404);

        $this->authorize('delete', $mantenimiento);

        $mantenimiento->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Mantenimiento eliminado correctamente.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aDto(Mantenimiento $m): array
    {
        return [
            'id' => $m->id,
            'fecha_realizado' => $m->fecha_realizado->toDateString(),
            'odometro' => $m->odometro,
            'proveedor' => $m->proveedor,
            'factura_numero' => $m->factura_numero,
            'costo_total' => $m->costo_total !== null ? (float) $m->costo_total : null,
            'observaciones' => $m->observaciones,
            'registrado_por' => $m->registradoPor?->name,
            'comprobante_url' => $m->getFirstMediaUrl('comprobante') ?: null,
            'fotos' => $m->getMedia('fotos')->map(fn ($media): array => [
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
            ])->values(),
            'items' => $m->items->map(fn ($item): array => [
                'id' => $item->id,
                'plantilla_id' => $item->plantilla_id,
                'nombre' => $item->nombre,
                'tipo_mantenimiento' => $item->tipo_mantenimiento,
                'costo' => $item->costo !== null ? (float) $item->costo : null,
            ])->values(),
        ];
    }

    /**
     * Mapea un servicio del plan (salida del service) al shape que consume el
     * frontend (`PlanMantenimiento`).
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function planItemDto(array $p): array
    {
        return [
            'plantilla_id' => $p['plantilla_id'],
            'nombre' => $p['nombre'],
            'tipo_mantenimiento' => $p['tipo_mantenimiento'],
            'periodicidad_km' => $p['intervalo_km'],
            'periodicidad_dias' => $p['intervalo_meses'] !== null ? $p['intervalo_meses'] * 30 : null,
            'proximo_km' => $p['proximo_odometro'],
            'ultimo_km' => $p['ultimo_odometro'],
            'ultimo_realizado' => $p['ultima_fecha'],
            'km_restantes' => $p['restante_km'],
            'dias_restantes' => $p['restante_dias'],
            'vencido' => $p['status'] === 'vencido',
            'progreso' => (int) round($p['progreso'] * 100),
        ];
    }

    private function mantenimientoPlantilla(Vehiculo $vehiculo): Mantenimiento
    {
        return (new Mantenimiento)->forceFill(['vehiculo_id' => $vehiculo->id]);
    }
}
