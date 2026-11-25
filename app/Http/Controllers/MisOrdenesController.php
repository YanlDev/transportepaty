<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\PrioridadOrden;
use App\Enums\UnidadMedida;
use App\Http\Requests\IniciarOrdenRequest;
use App\Http\Requests\PedirRepuestoRequest;
use App\Http\Requests\SubirFotosOrdenRequest;
use App\Http\Requests\TerminarOrdenRequest;
use App\Models\Llanta;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use App\Models\TareaOrden;
use App\Models\User;
use App\Services\AlertasDeTaller;
use App\Services\EjecucionDeOrden;
use App\Services\FileUploadService;
use App\Services\MaterialesDeOrden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * La pantalla del mecánico, hecha para el celular: las órdenes que le
 * asignaron y nada más. Cada una se trabaja en pasos —armar el requerimiento
 * (tareas, repuestos, fotos del antes) y enviarlo, empezar cuando operaciones
 * lo aprueba, marcar las tareas, terminar con la foto del después— y cada
 * paso queda con su usuario y su hora.
 *
 * No ve costos, ni el almacén, ni las órdenes de otros.
 *
 * Si el mecánico no puede anotar desde su celular, quien gestiona la orden
 * abre esta misma pantalla con `?por={mecánico}` y anota por él: mismas
 * reglas y mismos pasos, que quedan a nombre del mecánico y con quién los
 * anotó (ver `actores`).
 */
class MisOrdenesController extends Controller
{
    /** Cuántos días se siguen viendo las que ya se finalizaron. */
    private const DIAS_DE_TERMINADAS = 14;

    public function __construct(
        private readonly EjecucionDeOrden $ejecucion,
        private readonly AlertasDeTaller $alertas,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('verAsignadas', OrdenTrabajo::class);

        /** @var User $mecanico */
        $mecanico = $request->user();

        $ordenes = OrdenTrabajo::query()
            ->vigentes()
            ->deMecanico($mecanico)
            ->where(fn ($query) => $query
                ->where('estado', '!=', EstadoOrdenTrabajo::Finalizada)
                ->orWhere('revisado_at', '>=', now()->subDays(self::DIAS_DE_TERMINADAS)))
            ->with(['vehiculo:id,placa,marca,tipo', 'servicio:id,codigo', 'mecanicos:id,name'])
            ->withCount(['tareas', 'tareas as tareas_hechas_count' => fn ($query) => $query->whereNotNull('hecha_at')])
            ->orderByRaw("case prioridad when 'urgente' then 0 else 1 end")
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (OrdenTrabajo $orden): array => $this->resumen($orden, $mecanico));

        $grupo = fn (array $estados): array => $ordenes
            ->filter(fn (array $orden): bool => in_array($orden['estado'], $estados, true))
            ->values()
            ->all();

        return Inertia::render('mecanico/index', [
            // Lo que taller devolvió va primero: es lo que hay que corregir.
            'por_hacer' => [
                ...$grupo([EstadoOrdenTrabajo::Observada->value]),
                ...$grupo([EstadoOrdenTrabajo::EnProceso->value]),
                ...$grupo([EstadoOrdenTrabajo::Pendiente->value]),
                // Las pre-órdenes: falta armar y enviar el requerimiento.
                ...$grupo([EstadoOrdenTrabajo::Solicitada->value]),
            ],
            // En manos de operaciones: el requerimiento por aprobar y el
            // trabajo terminado por validar.
            'en_revision' => $grupo([EstadoOrdenTrabajo::Requerimiento->value, EstadoOrdenTrabajo::PorRevisar->value]),
            'terminadas' => array_reverse($grupo([EstadoOrdenTrabajo::Finalizada->value])),
        ]);
    }

    public function show(Request $request, OrdenTrabajo $orden, MaterialesDeOrden $materiales): Response
    {
        [$mecanico, $registra] = $this->actores($request, $orden);
        $orden->load(['vehiculo:id,placa,marca,tipo', 'servicio']);

        return Inertia::render('mecanico/orden', [
            'orden' => [
                ...$this->resumen($orden->loadCount(['tareas', 'tareas as tareas_hechas_count' => fn ($query) => $query->whereNotNull('hecha_at')]), $mecanico),
                'kilometraje' => $orden->kilometraje,
                'vehiculo_id' => $orden->vehiculo_id,
                // Con la orden aprobada, el mecánico ve las llantas de la unidad.
                'puede_ver_llantas' => ! $orden->estado->sinAprobar() && ($registra ?? $mecanico)->can('verUnidad', [Llanta::class, $orden->vehiculo]),
                'pide_km' => $orden->servicio !== null,
                'observacion' => $orden->observacion,
                ...$this->ejecucion->vista($orden),
                // Lo que el almacén ya le entregó para la orden y lo que el
                // servicio pide: sin costos.
                'materiales' => array_map(
                    fn (array $linea): array => [
                        'id' => $linea['id'],
                        'nombre' => $linea['nombre'],
                        'cantidad' => $linea['cantidad'] - $linea['devuelto'],
                        'unidad' => $linea['unidad'],
                        'vale' => $linea['vale'],
                    ],
                    $materiales->lineas($orden),
                ),
                'faltantes' => $orden->servicio === null ? [] : $materiales->faltantes($orden->servicio, $orden),
            ],
            // Quien gestiona la orden está anotando por este mecánico.
            'por' => $registra === null ? null : ['id' => $mecanico->id, 'nombre' => $mecanico->name],
            'maximo_fotos' => IniciarOrdenRequest::MAXIMO_FOTOS,
            // El catálogo del almacén, para pedir repuestos por su nombre:
            // sin costos, solo si hay o no hay.
            'articulos' => $this->ejecucion->articulosPedibles(),
            'unidades' => UnidadMedida::options(),
        ]);
    }

    /**
     * El mecánico envía su requerimiento a operaciones.
     */
    public function enviar(Request $request, OrdenTrabajo $orden): RedirectResponse
    {
        [$mecanico, $registra] = $this->actores($request, $orden);
        $this->ejecucion->enviarRequerimiento($orden, $mecanico, $registra);
        $this->alertas->requerimientoEnviado($orden, $registra ?? $mecanico);

        return $this->alSalir($orden, $registra)->with('toast', [
            'type' => 'success',
            'message' => $registra === null
                ? "Requerimiento de la {$orden->numero()} enviado a operaciones."
                : "Requerimiento de la {$orden->numero()} anotado por {$mecanico->name}: falta aprobarlo.",
        ]);
    }

    public function agregarTarea(Request $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->actores($request, $orden);

        $datos = $request->validate(
            ['descripcion' => ['required', 'string', 'min:3', 'max:500']],
            ['descripcion.required' => 'Escribe la tarea.', 'descripcion.min' => 'Describe un poco más la tarea.'],
        );

        $this->ejecucion->agregarTarea($orden, $datos['descripcion']);

        return back();
    }

    public function quitarTarea(Request $request, OrdenTrabajo $orden, TareaOrden $tarea): RedirectResponse
    {
        $this->actores($request, $orden);

        abort_unless($tarea->orden_trabajo_id === $orden->id, 404);

        $this->ejecucion->quitarTarea($tarea->setRelation('orden', $orden));

        return back();
    }

    public function pedirRepuesto(PedirRepuestoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        [$mecanico, $registra] = $this->actores($request, $orden);
        $repuesto = $this->ejecucion->pedirRepuesto($orden, $mecanico, $request->validated(), $registra);

        if ($repuesto->adicional) {
            $this->alertas->adicionalPedido($orden, $repuesto, $registra ?? $mecanico);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $repuesto->adicional
                ? 'Repuesto adicional pedido: operaciones lo tiene que aprobar.'
                : 'Repuesto agregado al requerimiento.',
        ]);
    }

    /**
     * El mecánico quita un repuesto que él pidió y nadie aprobó todavía.
     */
    public function quitarRepuesto(Request $request, OrdenTrabajo $orden, RepuestoOrden $repuesto): RedirectResponse
    {
        $this->actores($request, $orden);

        abort_unless($repuesto->orden_trabajo_id === $orden->id, 404);

        $this->ejecucion->quitarRepuesto($repuesto);

        return back();
    }

    /**
     * Empezar: las fotos del antes llegan con el mismo toque.
     */
    public function iniciar(IniciarOrdenRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        [$mecanico, $registra] = $this->actores($request, $orden);

        DB::transaction(function () use ($request, $orden, $subidas, $mecanico, $registra): void {
            $this->guardarFotos($request, $orden, 'antes', $subidas);
            $this->ejecucion->iniciar($orden->refresh(), $mecanico, $request->filled('kilometraje') ? $request->integer('kilometraje') : null, $registra);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "{$orden->numero()} en proceso."]);
    }

    /**
     * Terminar: las fotos del después y la nota llegan con el mismo toque.
     */
    public function terminar(TerminarOrdenRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        [$mecanico, $registra] = $this->actores($request, $orden);

        DB::transaction(function () use ($request, $orden, $subidas, $mecanico, $registra): void {
            $this->guardarFotos($request, $orden, 'despues', $subidas);
            $this->ejecucion->terminar(
                $orden->refresh(),
                $mecanico,
                $request->validated('nota'),
                $request->filled('kilometraje') ? $request->integer('kilometraje') : null,
                $registra,
            );
        });

        $this->alertas->trabajoTerminado($orden, $registra ?? $mecanico);

        return $this->alSalir($orden, $registra)->with('toast', [
            'type' => 'success',
            'message' => $registra === null
                ? "{$orden->numero()} terminada: taller la revisa."
                : "{$orden->numero()} terminada por {$mecanico->name}: falta validarla.",
        ]);
    }

    /**
     * Fotos que se suman mientras se trabaja.
     */
    public function fotos(SubirFotosOrdenRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        $this->actores($request, $orden);
        $this->exigirAbierta($orden);

        $this->guardarFotos($request, $orden, $request->validated('coleccion'), $subidas);

        return back()->with('toast', ['type' => 'success', 'message' => 'Fotos guardadas.']);
    }

    /**
     * El mecánico quita una foto que subió por error, mientras la orden
     * siga en sus manos.
     */
    public function eliminarFoto(Request $request, OrdenTrabajo $orden, Media $foto): RedirectResponse
    {
        $this->actores($request, $orden);
        $this->exigirAbierta($orden);

        abort_unless($foto->model_type === $orden->getMorphClass() && $foto->model_id === $orden->id, 404);

        $foto->delete();

        return back();
    }

    public function marcarTarea(Request $request, OrdenTrabajo $orden, TareaOrden $tarea): RedirectResponse
    {
        [$mecanico, $registra] = $this->actores($request, $orden);

        abort_unless($tarea->orden_trabajo_id === $orden->id, 404);

        $this->ejecucion->marcarTarea($tarea->setRelation('orden', $orden), $mecanico, $request->boolean('hecha'), $registra);

        return back();
    }

    /**
     * Quién hace el trabajo y, si lo anota otro por él, quién. Lo normal es
     * el mecánico desde su usuario. Con `por` es quien gestiona la orden
     * anotando por uno de los mecánicos asignados: el paso queda a nombre
     * del mecánico y aparte queda quién lo anotó.
     *
     * @return array{0: User, 1: User|null}
     */
    private function actores(Request $request, OrdenTrabajo $orden): array
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if (! $request->filled('por')) {
            $this->authorize('ejecutar', $orden);

            return [$usuario, null];
        }

        $mecanico = User::query()->findOrFail($request->integer('por'));
        $this->authorize('registrarPorMecanico', [$orden, $mecanico]);

        return [$mecanico, $mecanico->is($usuario) ? null : $usuario];
    }

    /**
     * A dónde se vuelve al cerrar un paso: el mecánico a su lista; quien
     * anotó por él, a la orden, donde le toca aprobar o validar.
     */
    private function alSalir(OrdenTrabajo $orden, ?User $registra): RedirectResponse
    {
        return $registra === null
            ? to_route('mis-ordenes.index')
            : to_route('mantenimiento.ordenes.edit', $orden);
    }

    /**
     * Lo que se ve de una orden en la lista del mecánico.
     *
     * @return array<string, mixed>
     */
    private function resumen(OrdenTrabajo $orden, User $mecanico): array
    {
        return [
            'id' => $orden->id,
            'numero' => $orden->numero(),
            'placa' => $orden->vehiculo->placa,
            'marca' => $orden->vehiculo->marca,
            'tipo_unidad' => $orden->vehiculo->tipo->label(),
            'tipo' => $orden->tipo->label(),
            'servicio' => $orden->servicio?->codigo,
            'estado' => $orden->estado->value,
            'estado_label' => $orden->estado->label(),
            'urgente' => $orden->prioridad === PrioridadOrden::Urgente,
            'fecha' => $orden->fecha->toDateString(),
            'trabajo' => $orden->trabajo,
            'tareas_total' => (int) ($orden->tareas_count ?? 0),
            'tareas_hechas' => (int) ($orden->tareas_hechas_count ?? 0),
            'companeros' => $orden->mecanicos
                ->reject(fn (User $otro): bool => $otro->id === $mecanico->id)
                ->pluck('name')
                ->values()
                ->all(),
            'observacion_revision' => $orden->estado === EstadoOrdenTrabajo::Observada ? $orden->observacion_revision : null,
            'devolucion_requerimiento' => $orden->estado === EstadoOrdenTrabajo::Solicitada ? $orden->devolucion_requerimiento : null,
        ];
    }

    private function guardarFotos(Request $request, OrdenTrabajo $orden, string $coleccion, FileUploadService $subidas): void
    {
        foreach ($request->file('fotos', []) as $indice => $foto) {
            $subidas->guardar($orden, $foto, $coleccion, "fotos.{$indice}");
        }
    }

    /**
     * Una orden que ya pasó a taller (por revisar o finalizada) no se toca.
     */
    private function exigirAbierta(OrdenTrabajo $orden): void
    {
        if (! in_array($orden->estado, [EstadoOrdenTrabajo::Solicitada, EstadoOrdenTrabajo::Pendiente, EstadoOrdenTrabajo::EnProceso, EstadoOrdenTrabajo::Observada], true)) {
            throw ValidationException::withMessages([
                'fotos' => "La {$orden->numero()} está ".mb_strtolower($orden->estado->label()).': ya no se le cambian las fotos.',
            ]);
        }
    }
}
