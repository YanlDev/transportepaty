<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\PrioridadOrden;
use App\Http\Requests\IniciarOrdenRequest;
use App\Http\Requests\SubirFotosOrdenRequest;
use App\Http\Requests\TerminarOrdenRequest;
use App\Models\OrdenTrabajo;
use App\Models\TareaOrden;
use App\Models\User;
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
 * La pantalla del mecánico, hecha para el celular: las órdenes que taller le
 * asignó y nada más. Cada una se trabaja en pasos —empezar con la foto del
 * antes, marcar las tareas, terminar con la foto del después— y cada paso
 * queda con su usuario y su hora.
 *
 * No ve costos, ni el almacén, ni las órdenes de otros.
 */
class MisOrdenesController extends Controller
{
    /** Cuántos días se siguen viendo las que ya se finalizaron. */
    private const DIAS_DE_TERMINADAS = 14;

    public function __construct(private readonly EjecucionDeOrden $ejecucion) {}

    public function index(Request $request): Response
    {
        $this->authorize('verAsignadas', OrdenTrabajo::class);

        /** @var User $mecanico */
        $mecanico = $request->user();

        $ordenes = OrdenTrabajo::query()
            ->vigentes()
            ->deMecanico($mecanico)
            ->where('estado', '!=', EstadoOrdenTrabajo::Solicitada)
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
            ],
            'en_revision' => $grupo([EstadoOrdenTrabajo::PorRevisar->value]),
            'terminadas' => array_reverse($grupo([EstadoOrdenTrabajo::Finalizada->value])),
        ]);
    }

    public function show(Request $request, OrdenTrabajo $orden, MaterialesDeOrden $materiales): Response
    {
        $this->authorize('ejecutar', $orden);

        /** @var User $mecanico */
        $mecanico = $request->user();
        $orden->load(['vehiculo:id,placa,marca,tipo', 'servicio']);

        return Inertia::render('mecanico/orden', [
            'orden' => [
                ...$this->resumen($orden->loadCount(['tareas', 'tareas as tareas_hechas_count' => fn ($query) => $query->whereNotNull('hecha_at')]), $mecanico),
                'kilometraje' => $orden->kilometraje,
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
            'maximo_fotos' => IniciarOrdenRequest::MAXIMO_FOTOS,
        ]);
    }

    /**
     * Empezar: las fotos del antes llegan con el mismo toque.
     */
    public function iniciar(IniciarOrdenRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('ejecutar', $orden);

        /** @var User $mecanico */
        $mecanico = $request->user();

        DB::transaction(function () use ($request, $orden, $subidas, $mecanico): void {
            $this->guardarFotos($request, $orden, 'antes', $subidas);
            $this->ejecucion->iniciar($orden->refresh(), $mecanico, $request->filled('kilometraje') ? $request->integer('kilometraje') : null);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "{$orden->numero()} en proceso."]);
    }

    /**
     * Terminar: las fotos del después y la nota llegan con el mismo toque.
     */
    public function terminar(TerminarOrdenRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('ejecutar', $orden);

        /** @var User $mecanico */
        $mecanico = $request->user();

        DB::transaction(function () use ($request, $orden, $subidas, $mecanico): void {
            $this->guardarFotos($request, $orden, 'despues', $subidas);
            $this->ejecucion->terminar(
                $orden->refresh(),
                $mecanico,
                $request->validated('nota'),
                $request->filled('kilometraje') ? $request->integer('kilometraje') : null,
            );
        });

        return to_route('mis-ordenes.index')->with('toast', [
            'type' => 'success',
            'message' => "{$orden->numero()} terminada: taller la revisa.",
        ]);
    }

    /**
     * Fotos que se suman mientras se trabaja.
     */
    public function fotos(SubirFotosOrdenRequest $request, OrdenTrabajo $orden, FileUploadService $subidas): RedirectResponse
    {
        $this->authorize('ejecutar', $orden);
        $this->exigirAbierta($orden);

        $this->guardarFotos($request, $orden, $request->validated('coleccion'), $subidas);

        return back()->with('toast', ['type' => 'success', 'message' => 'Fotos guardadas.']);
    }

    /**
     * El mecánico quita una foto que subió por error, mientras la orden
     * siga en sus manos.
     */
    public function eliminarFoto(OrdenTrabajo $orden, Media $foto): RedirectResponse
    {
        $this->authorize('ejecutar', $orden);
        $this->exigirAbierta($orden);

        abort_unless($foto->model_type === $orden->getMorphClass() && $foto->model_id === $orden->id, 404);

        $foto->delete();

        return back();
    }

    public function marcarTarea(Request $request, OrdenTrabajo $orden, TareaOrden $tarea): RedirectResponse
    {
        $this->authorize('ejecutar', $orden);

        abort_unless($tarea->orden_trabajo_id === $orden->id, 404);

        /** @var User $mecanico */
        $mecanico = $request->user();
        $this->ejecucion->marcarTarea($tarea->setRelation('orden', $orden), $mecanico, $request->boolean('hecha'));

        return back();
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
        if (! in_array($orden->estado, [EstadoOrdenTrabajo::Pendiente, EstadoOrdenTrabajo::EnProceso, EstadoOrdenTrabajo::Observada], true)) {
            throw ValidationException::withMessages([
                'fotos' => "La {$orden->numero()} está ".mb_strtolower($orden->estado->label()).': ya no se le cambian las fotos.',
            ]);
        }
    }
}
