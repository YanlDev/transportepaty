<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreOrdenTrabajoRequest;
use App\Http\Requests\UpdateOrdenTrabajoRequest;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\ServicioMantenimiento;
use App\Models\Vehiculo;
use App\Services\ProximoMantenimiento;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * El cuadro de trabajos de taller, desde el celular o la tablet: una orden
 * por trabajo, con su panel fotográfico de antes y después.
 */
class OrdenTrabajoController extends Controller
{
    public function create(Request $request, ProximoMantenimiento $mantenimiento): Response
    {
        $this->authorize('create', OrdenTrabajo::class);

        // Desde la ficha de una unidad se llega con la unidad y su próximo
        // servicio ya elegidos: es casi siempre lo que se va a registrar.
        $vehiculo = Vehiculo::query()->find($request->integer('vehiculo'));
        $estado = $vehiculo === null ? null : $mantenimiento->paraVehiculo($vehiculo);

        return Inertia::render('mantenimiento/orden', [
            'orden' => null,
            'inicial' => [
                'vehiculo_id' => $vehiculo?->id,
                'tipo' => $estado['proximo'] ?? null ? TipoOrdenTrabajo::Preventiva->value : TipoOrdenTrabajo::Correctiva->value,
                'servicio_mantenimiento_id' => $estado['proximo']['id'] ?? null,
                'trabajo' => $estado['proximo']['nombre'] ?? '',
                'kilometraje' => $estado['km_actual'] ?? null,
                'fecha' => RelojOperativo::hoy(),
            ],
            ...$this->catalogos(),
        ]);
    }

    public function store(StoreOrdenTrabajoRequest $request): RedirectResponse
    {
        $this->authorize('create', OrdenTrabajo::class);

        $orden = DB::transaction(function () use ($request): OrdenTrabajo {
            $orden = OrdenTrabajo::create([
                ...$request->safe()->except(['fotos_antes', 'fotos_despues']),
                'user_id' => $request->user()?->id,
            ]);

            $this->guardarFotos($request, $orden);

            return $orden;
        });

        return to_route('mantenimiento.show', $orden->vehiculo_id)->with('toast', [
            'type' => 'success',
            'message' => 'Orden de trabajo registrada.',
        ]);
    }

    public function edit(OrdenTrabajo $orden): Response
    {
        $this->authorize('update', $orden);

        $orden->load('media');

        return Inertia::render('mantenimiento/orden', [
            'orden' => [
                'id' => $orden->id,
                'vehiculo_id' => $orden->vehiculo_id,
                'fotos' => $orden->media
                    ->map(fn (Media $foto): array => [
                        'id' => $foto->id,
                        'coleccion' => $foto->collection_name,
                        'url' => $foto->getUrl(),
                    ])
                    ->values()
                    ->all(),
            ],
            'inicial' => [
                'vehiculo_id' => $orden->vehiculo_id,
                'tipo' => $orden->tipo->value,
                'servicio_mantenimiento_id' => $orden->servicio_mantenimiento_id,
                'estado' => $orden->estado->value,
                'fecha' => $orden->fecha->toDateString(),
                'kilometraje' => $orden->kilometraje,
                'inicio' => $orden->inicio?->format('Y-m-d\TH:i'),
                'termino' => $orden->termino?->format('Y-m-d\TH:i'),
                'trabajo' => $orden->trabajo,
                'tecnico' => $orden->tecnico,
                'observacion' => $orden->observacion,
            ],
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateOrdenTrabajoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('update', $orden);

        DB::transaction(function () use ($request, $orden): void {
            $orden->update($request->safe()->except(['fotos_antes', 'fotos_despues']));

            $this->guardarFotos($request, $orden);
        });

        return to_route('mantenimiento.show', $orden->vehiculo_id)->with('toast', [
            'type' => 'success',
            'message' => 'Orden de trabajo actualizada.',
        ]);
    }

    public function destroy(OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('delete', $orden);

        $orden->delete();

        return to_route('mantenimiento.show', $orden->vehiculo_id)->with('toast', [
            'type' => 'success',
            'message' => 'Orden de trabajo eliminada.',
        ]);
    }

    /**
     * Quita una foto del panel sin tocar el resto de la orden.
     */
    public function eliminarFoto(OrdenTrabajo $orden, Media $foto): RedirectResponse
    {
        $this->authorize('update', $orden);

        abort_unless($foto->model_type === $orden->getMorphClass() && $foto->model_id === $orden->id, 404);

        $foto->delete();

        return back();
    }

    private function guardarFotos(StoreOrdenTrabajoRequest $request, OrdenTrabajo $orden): void
    {
        foreach (['fotos_antes' => 'antes', 'fotos_despues' => 'despues'] as $campo => $coleccion) {
            foreach ($request->file($campo, []) as $foto) {
                $orden->addMedia($foto)->toMediaCollection($coleccion);
            }
        }
    }

    /**
     * Las unidades y los servicios del plan de cada marca, para que el
     * formulario ofrezca solo los del plan de la unidad elegida.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'tractos' => Vehiculo::query()
                ->where('tipo', TipoVehiculo::Tracto)
                ->orderBy('placa')
                ->get(['id', 'placa', 'marca'])
                ->map(fn (Vehiculo $vehiculo): array => [
                    'id' => $vehiculo->id,
                    'placa' => $vehiculo->placa,
                    'marca' => $vehiculo->marca === null ? null : mb_strtoupper(trim($vehiculo->marca)),
                ])
                ->all(),
            'servicios' => PlanMantenimiento::query()
                ->with('servicios')
                ->get()
                ->mapWithKeys(fn (PlanMantenimiento $plan): array => [
                    $plan->marca => $plan->servicios
                        ->map(fn (ServicioMantenimiento $servicio): array => [
                            'id' => $servicio->id,
                            'codigo' => $servicio->codigo,
                            'nombre' => $servicio->nombre,
                            'intervalo_km' => $servicio->intervalo_km,
                        ])
                        ->all(),
                ])
                ->all(),
            'tipos' => TipoOrdenTrabajo::options(),
            'estados' => EstadoOrdenTrabajo::options(),
        ];
    }
}
