<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\StoreOrdenTrabajoRequest;
use App\Http\Requests\UpdateOrdenTrabajoRequest;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\SalidaAlmacen;
use App\Models\ServicioMantenimiento;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use App\Services\FileUploadService;
use App\Services\MaterialesDeOrden;
use App\Services\ProximoMantenimiento;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * El cuadro de trabajos de taller, desde el celular o la tablet: una orden
 * por trabajo, con su panel fotográfico de antes y después.
 *
 * Los repuestos no se anotan acá: salen del almacén con un vale a nombre de
 * la orden, y la orden muestra lo que le salió y lo que costó.
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

    public function store(StoreOrdenTrabajoRequest $request, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('create', OrdenTrabajo::class);

        $orden = DB::transaction(function () use ($request, $subidas): OrdenTrabajo {
            $orden = OrdenTrabajo::create([
                ...$request->safe()->except(['fotos_antes', 'fotos_despues']),
                'user_id' => $request->user()?->id,
            ]);

            $this->guardarFotos($request, $orden, $subidas);

            return $orden;
        });

        return to_route('mantenimiento.show', $orden->vehiculo_id)->with('toast', [
            'type' => 'success',
            'message' => 'Orden de trabajo registrada.',
        ]);
    }

    public function edit(Request $request, OrdenTrabajo $orden, MaterialesDeOrden $materiales): Response
    {
        $this->authorize('update', $orden);

        $orden->load(['media', 'servicio']);
        $veCostos = $request->user()?->can('verCostos', Articulo::class) ?? false;

        return Inertia::render('mantenimiento/orden', [
            'orden' => [
                'id' => $orden->id,
                'numero' => $orden->numero(),
                'vehiculo_id' => $orden->vehiculo_id,
                'puede_anular' => $request->user()?->can('anular', $orden) ?? false,
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
                'justificacion_materiales' => $orden->justificacion_materiales,
            ],
            'materiales' => [
                'lineas' => array_map(
                    fn (array $linea): array => [...$linea, 'costo' => $veCostos ? $linea['costo'] : null],
                    $materiales->lineas($orden),
                ),
                'costo' => $veCostos ? $materiales->costo($orden) : null,
                // Los servicios de terceros pedidos para la orden.
                'terceros' => array_map(
                    fn (array $servicio): array => [...$servicio, 'total' => $veCostos ? $servicio['total'] : null],
                    $materiales->terceros([$orden->id]),
                ),
                'puede_pedir_compra' => $request->user()?->can('create', ValeCompra::class) ?? false,
                // Lo que pide el nivel guardado: si se cambia el servicio,
                // se actualiza al guardar.
                'requeridos' => $orden->servicio === null ? [] : $materiales->requeridos($orden->servicio, $orden),
                'puede_despachar' => $request->user()?->can('create', SalidaAlmacen::class) ?? false,
                've_almacen' => $request->user()?->can('viewAny', SalidaAlmacen::class) ?? false,
            ],
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateOrdenTrabajoRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('update', $orden);

        DB::transaction(function () use ($request, $orden, $subidas): void {
            $orden->update($request->safe()->except(['fotos_antes', 'fotos_despues']));

            $this->guardarFotos($request, $orden, $subidas);
        });

        return to_route('mantenimiento.show', $orden->vehiculo_id)->with('toast', [
            'type' => 'success',
            'message' => 'Orden de trabajo actualizada.',
        ]);
    }

    /**
     * La orden no se borra: queda a la vista con su motivo y deja de contar
     * para el plan. Con vales vigentes no se puede: lo que salió del almacén
     * a su nombre tiene que volver (anulando el vale) antes de anularla.
     */
    public function anular(AnularDocumentoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('anular', $orden);

        $vales = $orden->salidas()->vigentes()->get();

        if ($vales->isNotEmpty()) {
            throw ValidationException::withMessages([
                'motivo' => 'La orden tiene vales de almacén vigentes ('
                    .$vales->map(fn (SalidaAlmacen $vale): string => $vale->numero())->implode(', ')
                    .'): anúlalos primero para que lo despachado vuelva al stock.',
            ]);
        }

        $orden->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $request->validated('motivo'),
        ]);

        return to_route('mantenimiento.show', $orden->vehiculo_id)->with('toast', [
            'type' => 'success',
            'message' => "{$orden->numero()} anulada: ya no cuenta para el plan de la unidad.",
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

    private function guardarFotos(StoreOrdenTrabajoRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): void
    {
        foreach (['fotos_antes' => 'antes', 'fotos_despues' => 'despues'] as $campo => $coleccion) {
            foreach ($request->file($campo, []) as $indice => $foto) {
                $subidas->guardar($orden, $foto, $coleccion, "{$campo}.{$indice}");
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
