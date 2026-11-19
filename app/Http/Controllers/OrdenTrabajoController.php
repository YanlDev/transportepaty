<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\PrioridadOrden;
use App\Enums\TipoOrdenTrabajo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\ObservarOrdenRequest;
use App\Http\Requests\StoreOrdenTrabajoRequest;
use App\Http\Requests\UpdateOrdenTrabajoRequest;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\RepuestoOrden;
use App\Models\SalidaAlmacen;
use App\Models\ServicioMantenimiento;
use App\Models\User;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use App\Services\EjecucionDeOrden;
use App\Services\FileUploadService;
use App\Services\MaterialesDeOrden;
use App\Services\ProximoMantenimiento;
use App\Services\RelojOperativo;
use Illuminate\Database\Eloquent\Builder;
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
    /** Lo que no es columna de la orden: va a sus fotos, mecánicos y tareas. */
    private const FUERA_DE_LA_ORDEN = ['fotos_antes', 'fotos_despues', 'mecanicos', 'responsable_id', 'tareas'];

    public function __construct(private readonly EjecucionDeOrden $ejecucion) {}

    /**
     * Todas las órdenes, con lo que hay que atender primero: las solicitudes
     * por aceptar y lo que los mecánicos terminaron y espera revisión.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OrdenTrabajo::class);

        $estado = $request->string('estado')->toString() ?: 'abiertas';
        $mecanico = $request->integer('mecanico') ?: null;
        $placa = mb_strtoupper(trim($request->string('q')->toString()));

        $ordenes = OrdenTrabajo::query()
            ->with(['vehiculo:id,placa,tipo', 'servicio:id,codigo', 'mecanicos:id,name', 'creador:id,name'])
            ->withCount('media')
            ->when($estado === 'abiertas', fn (Builder $query) => $query->vigentes()->where('estado', '!=', EstadoOrdenTrabajo::Finalizada))
            ->when($estado === 'anuladas', fn (Builder $query) => $query->whereNotNull('anulado_at'))
            ->when(EstadoOrdenTrabajo::tryFrom($estado) !== null, fn (Builder $query) => $query->vigentes()->where('estado', $estado))
            ->when($mecanico !== null, fn (Builder $query) => $query->whereHas('mecanicos', fn (Builder $mecanicos) => $mecanicos->whereKey($mecanico)))
            ->when($placa !== '', fn (Builder $query) => $query->whereHas('vehiculo', fn (Builder $vehiculo) => $vehiculo->withTrashed()->where('placa', 'like', "%{$placa}%")))
            ->orderByRaw("case estado when 'requerimiento' then 0 when 'por_revisar' then 1 when 'observada' then 2 when 'en_proceso' then 3 when 'pendiente' then 4 when 'solicitada' then 5 else 6 end")
            ->orderByRaw("case prioridad when 'urgente' then 0 else 1 end")
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $puedeEditar = $request->user()?->can('create', OrdenTrabajo::class) ?? false;

        return Inertia::render('mantenimiento/ordenes', [
            'ordenes' => $ordenes->through(fn (OrdenTrabajo $orden): array => [
                'id' => $orden->id,
                'numero' => $orden->numero(),
                'vehiculo_id' => $orden->vehiculo_id,
                'placa' => $orden->vehiculo->placa,
                'tipo_unidad' => $orden->vehiculo->tipo->label(),
                'tipo' => $orden->tipo->label(),
                'servicio' => $orden->servicio?->codigo,
                'estado' => $orden->estaAnulado() ? 'anulada' : $orden->estado->value,
                'estado_label' => $orden->estaAnulado() ? 'Anulada' : $orden->estado->label(),
                'urgente' => $orden->prioridad === PrioridadOrden::Urgente,
                'fecha' => $orden->fecha->toDateString(),
                'trabajo' => $orden->trabajo,
                'mecanicos' => $orden->mecanicos->pluck('name')->all(),
                'solicitante' => $orden->estado === EstadoOrdenTrabajo::Solicitada ? $orden->creador?->name : null,
                'fotos' => $orden->media_count,
            ]),
            'filtros' => ['estado' => $estado, 'mecanico' => $mecanico, 'q' => $placa],
            'estados' => [
                ['value' => 'abiertas', 'label' => 'Abiertas'],
                ...EstadoOrdenTrabajo::options(),
                ['value' => 'anuladas', 'label' => 'Anuladas'],
                ['value' => 'todas', 'label' => 'Todas'],
            ],
            'mecanicos' => $this->mecanicosDisponibles(),
            // Cuántas esperan a taller, para que salten aunque se esté
            // mirando otro filtro.
            'por_atender' => [
                'por_revisar' => OrdenTrabajo::query()->vigentes()->where('estado', EstadoOrdenTrabajo::PorRevisar)->count(),
                'requerimientos' => OrdenTrabajo::query()->vigentes()->where('estado', EstadoOrdenTrabajo::Requerimiento)->count(),
                'adicionales' => RepuestoOrden::query()
                    ->whereNull('aprobado_at')
                    ->whereHas('orden', fn (Builder $orden) => $orden->enTaller()->whereNotIn('estado', [EstadoOrdenTrabajo::Solicitada, EstadoOrdenTrabajo::Requerimiento]))
                    ->count(),
            ],
            'puede' => ['editar' => $puedeEditar],
        ]);
    }

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
                // Lo normal es una pre-orden: el mecánico arma el
                // requerimiento y operaciones lo aprueba.
                'estado' => EstadoOrdenTrabajo::Solicitada->value,
                'prioridad' => PrioridadOrden::Normal->value,
                'mecanicos' => [],
                'responsable_id' => null,
                'tareas' => [],
            ],
            'ejecucion' => null,
            ...$this->catalogos(),
        ]);
    }

    public function store(StoreOrdenTrabajoRequest $request, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('create', OrdenTrabajo::class);

        $orden = DB::transaction(function () use ($request, $subidas): OrdenTrabajo {
            $orden = OrdenTrabajo::create([
                ...$this->datosDeLaOrden($request),
                ...$this->revision($request, null),
                'user_id' => $request->user()?->id,
            ]);

            $this->guardarFotos($request, $orden, $subidas);
            $this->ejecucion->asignar($orden, $this->mecanicosPedidos($request), $request->integer('responsable_id') ?: null);

            // Una preventiva nace con la lista de su nivel, si no se le
            // escribió otra.
            $tareas = $request->validated('tareas') ?? [];
            $this->ejecucion->guardarTareas($orden, $tareas !== [] || $orden->servicio === null
                ? $tareas
                : $this->ejecucion->tareasDelPlan($orden->servicio));

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

        $orden->load(['media', 'servicio', 'mecanicos:id,name', 'tareas']);
        $veCostos = $request->user()?->can('verCostos', Articulo::class) ?? false;

        return Inertia::render('mantenimiento/orden', [
            'orden' => [
                'id' => $orden->id,
                'numero' => $orden->numero(),
                'vehiculo_id' => $orden->vehiculo_id,
                'puede_anular' => $request->user()?->can('anular', $orden) ?? false,
                'puede_observar' => $request->user()?->can('observar', $orden) ?? false,
                'puede_rechazar' => $request->user()?->can('rechazar', $orden) ?? false,
                'puede_aprobar_requerimiento' => $request->user()?->can('aprobarRequerimiento', $orden) ?? false,
                'estado' => $orden->estado->value,
                'estado_label' => $orden->estado->label(),
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
                'prioridad' => $orden->prioridad->value,
                'mecanicos' => $orden->mecanicos->modelKeys(),
                'responsable_id' => $orden->mecanicos->first(fn (User $mecanico): bool => (bool) $mecanico->pivot->responsable)?->id,
                'tareas' => $orden->tareas
                    ->map(fn ($tarea): array => ['id' => $tarea->id, 'descripcion' => $tarea->descripcion])
                    ->all(),
            ],
            'ejecucion' => $this->ejecucion->vista($orden),
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
            ...$this->catalogos($orden),
        ]);
    }

    public function update(UpdateOrdenTrabajoRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('update', $orden);

        DB::transaction(function () use ($request, $orden, $subidas): void {
            $orden->update([
                ...$this->datosDeLaOrden($request),
                ...$this->revision($request, $orden),
            ]);

            $this->guardarFotos($request, $orden, $subidas);

            // Lo que el formulario no manda no se toca.
            if ($request->has('mecanicos')) {
                $this->ejecucion->asignar($orden, $this->mecanicosPedidos($request), $request->integer('responsable_id') ?: null);
            }

            if ($request->has('tareas')) {
                $this->ejecucion->guardarTareas($orden, $request->validated('tareas') ?? []);
            }
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
     * Taller revisó lo que el mecánico terminó y no lo aprueba: vuelve al
     * mecánico con lo que hay que corregir. (Aprobar es finalizar la orden
     * desde su formulario, con las mismas reglas de siempre.)
     */
    public function observar(ObservarOrdenRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('observar', $orden);

        /** @var User $revisor */
        $revisor = $request->user();
        $this->ejecucion->observar($orden, $revisor, $request->validated('motivo'));

        return to_route('mantenimiento.ordenes.index')->with('toast', [
            'type' => 'warning',
            'message' => "{$orden->numero()} observada: vuelve al mecánico.",
        ]);
    }

    /**
     * Operaciones aprueba el requerimiento del mecánico: la pre-orden pasa a
     * ser una orden de trabajo, lista para empezar.
     */
    public function aprobarRequerimiento(Request $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('aprobarRequerimiento', $orden);

        /** @var User $aprobador */
        $aprobador = $request->user();
        DB::transaction(fn () => $this->ejecucion->aprobarRequerimiento($orden, $aprobador));

        return to_route('mantenimiento.ordenes.edit', $orden)->with('toast', [
            'type' => 'success',
            'message' => "Requerimiento aprobado: la {$orden->numero()} ya es una orden de trabajo.",
        ]);
    }

    /**
     * El requerimiento vuelve al mecánico con lo que hay que cambiar.
     */
    public function devolverRequerimiento(ObservarOrdenRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('aprobarRequerimiento', $orden);

        /** @var User $aprobador */
        $aprobador = $request->user();
        $this->ejecucion->devolverRequerimiento($orden, $aprobador, $request->validated('motivo'));

        return to_route('mantenimiento.ordenes.index')->with('toast', [
            'type' => 'warning',
            'message' => "Requerimiento de la {$orden->numero()} devuelto al mecánico.",
        ]);
    }

    /**
     * Un repuesto que el mecánico pidió con la orden ya aprobada.
     */
    public function aprobarRepuesto(Request $request, OrdenTrabajo $orden, RepuestoOrden $repuesto): RedirectResponse
    {
        $this->authorize('update', $orden);

        abort_unless($repuesto->orden_trabajo_id === $orden->id, 404);

        /** @var User $aprobador */
        $aprobador = $request->user();
        $this->ejecucion->aprobarRepuesto($repuesto->setRelation('orden', $orden), $aprobador);

        return back()->with('toast', ['type' => 'success', 'message' => "Aprobado: {$repuesto->descripcion}."]);
    }

    /**
     * Un repuesto pedido que no se va a dar: se quita mientras no esté
     * aprobado.
     */
    public function quitarRepuesto(OrdenTrabajo $orden, RepuestoOrden $repuesto): RedirectResponse
    {
        $this->authorize('update', $orden);

        abort_unless($repuesto->orden_trabajo_id === $orden->id, 404);

        $this->ejecucion->quitarRepuesto($repuesto);

        return back()->with('toast', ['type' => 'success', 'message' => 'Repuesto quitado del pedido.']);
    }

    /**
     * Una pre-orden que no se va a atender: queda anulada con el motivo, a
     * la vista de quien la pidió.
     */
    public function rechazar(AnularDocumentoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->authorize('rechazar', $orden);

        $orden->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $request->validated('motivo'),
        ]);

        return to_route('mantenimiento.ordenes.index')->with('toast', [
            'type' => 'success',
            'message' => "Pre-orden {$orden->numero()} rechazada.",
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
     * Las columnas de la orden que llegan del formulario.
     *
     * @return array<string, mixed>
     */
    private function datosDeLaOrden(StoreOrdenTrabajoRequest $request): array
    {
        $datos = $request->safe()->except(self::FUERA_DE_LA_ORDEN);

        if (($datos['prioridad'] ?? null) === null) {
            unset($datos['prioridad']);
        }

        return $datos;
    }

    /**
     * Quien deja la orden finalizada es quien la revisó: queda su usuario y
     * la hora. Si se reabre, la revisión anterior se borra.
     *
     * @return array<string, mixed>
     */
    private function revision(StoreOrdenTrabajoRequest $request, ?OrdenTrabajo $orden): array
    {
        $finaliza = $request->input('estado') === EstadoOrdenTrabajo::Finalizada->value;
        $yaEstaba = $orden?->estado === EstadoOrdenTrabajo::Finalizada;

        return match (true) {
            $finaliza && ! $yaEstaba => ['revisado_por' => $request->user()?->id, 'revisado_at' => now(), 'observacion_revision' => null],
            ! $finaliza && $yaEstaba => ['revisado_por' => null, 'revisado_at' => null],
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    private function mecanicosPedidos(StoreOrdenTrabajoRequest $request): array
    {
        return array_map(intval(...), $request->validated('mecanicos') ?? []);
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function mecanicosDisponibles(): array
    {
        return $this->ejecucion->mecanicosDisponibles()
            ->map(fn (User $mecanico): array => ['id' => $mecanico->id, 'nombre' => $mecanico->name])
            ->values()
            ->all();
    }

    /**
     * Las unidades (tractos y carretas) y los servicios del plan de cada
     * marca, para que el formulario ofrezca solo los del plan de la unidad
     * elegida. Una carreta no tiene plan por km: sus órdenes son correctivas.
     *
     * @return array<string, mixed>
     */
    private function catalogos(?OrdenTrabajo $orden = null): array
    {
        // Los estados que taller elige a mano; si la orden está en uno que
        // sale de una acción (por revisar, observada…), se puede dejar así.
        $estados = EstadoOrdenTrabajo::deTaller();

        if ($orden === null) {
            $estados = [EstadoOrdenTrabajo::Solicitada, ...$estados];
        } elseif (! in_array($orden->estado, $estados, true)) {
            $estados = [$orden->estado, ...$estados];
        }

        return [
            'tractos' => Vehiculo::query()
                ->when($orden !== null, fn (Builder $query) => $query->withTrashed()->whereKey($orden?->vehiculo_id))
                ->orderByRaw('case tipo when ? then 0 else 1 end', [TipoVehiculo::Tracto->value])
                ->orderBy('placa')
                ->get(['id', 'placa', 'marca', 'tipo'])
                ->map(fn (Vehiculo $vehiculo): array => [
                    'id' => $vehiculo->id,
                    'placa' => $vehiculo->placa,
                    'marca' => $vehiculo->marca === null ? null : mb_strtoupper(trim($vehiculo->marca)),
                    'tipo' => $vehiculo->tipo->value,
                    'tipo_label' => $vehiculo->tipo->label(),
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
            'estados' => array_map(
                fn (EstadoOrdenTrabajo $estado): array => ['value' => $estado->value, 'label' => $estado->label()],
                $estados,
            ),
            'prioridades' => PrioridadOrden::options(),
            'mecanicos' => $this->mecanicosDisponibles(),
        ];
    }
}
