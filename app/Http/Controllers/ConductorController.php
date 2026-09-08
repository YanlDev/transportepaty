<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoConductor;
use App\Http\Requests\StoreConductorRequest;
use App\Http\Requests\UpdateConductorRequest;
use App\Models\Asistencia;
use App\Models\Conductor;
use App\Models\User;
use App\Models\Viaje;
use App\Services\CalendarioAsistenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConductorController extends Controller
{
    public function __construct(private readonly CalendarioAsistenciaService $calendarioService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Conductor::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $conductores = Conductor::query()
            // Una sola carga alimenta el semáforo documental de cada fila.
            ->with(['documentos:id,conductor_id,tipo,numero,fecha_vencimiento'])
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                // La lista muestra "APELLIDOS NOMBRES" (como en las GR), así que
                // hay que poder buscar por ese nombre completo y no solo por
                // nombres o apellidos por separado.
                $comodin = '%'.mb_strtolower($buscar).'%';

                $query->where(function ($query) use ($buscar, $comodin): void {
                    $query->whereLike('nombres', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('apellidos', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('documento', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('licencia', "%{$buscar}%", caseSensitive: false)
                        ->orWhereRaw("LOWER(apellidos || ' ' || nombres) LIKE ?", [$comodin])
                        ->orWhereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$comodin]);
                });
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Conductor $conductor): array => [
                'id' => $conductor->id,
                'nombres' => $conductor->nombres,
                'apellidos' => $conductor->apellidos,
                'nombre_completo' => $conductor->nombre_completo,
                'documento' => $conductor->documento,
                'licencia' => $conductor->licencia,
                'categoria_licencia' => $conductor->categoria_licencia,
                'licencia_vence' => $conductor->licencia_vence?->toDateString(),
                'telefono' => $conductor->telefono,
                'email' => $conductor->email,
                'procedencia' => $conductor->procedencia,
                'activo' => $conductor->activo,
                'fecha_baja' => $conductor->fecha_baja?->toDateString(),
                'motivo_baja' => $conductor->motivo_baja,
                'documentacion' => $conductor->estadoDocumental(),
            ]);

        return Inertia::render('conductores/index', [
            'conductores' => $conductores,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Cuántos viajes se listan en la tarjeta de «viajes recientes» de la
     * ficha: un vistazo a lo último que manejó, no el historial completo —
     * ese vive en `/viajes` filtrado por su nombre.
     */
    private const VIAJES_RECIENTES = 5;

    /**
     * La ficha del conductor: su expediente completo en una sola pantalla
     * —datos, indicadores, últimos viajes, asistencia y documentos—, sin
     * saltar de página. La asistencia solo se arma para quien puede verla
     * (admin, no visor).
     */
    public function show(Request $request, Conductor $conductor): Response
    {
        $this->authorize('view', $conductor);

        $conductor->load('documentos.media');

        $puedeVerAsistencia = $request->user()->can('viewAny', Asistencia::class);

        return Inertia::render('conductores/show', [
            'conductor' => $conductor,
            'documentacion' => $conductor->estadoDocumental(),
            'ranuras' => $conductor->ranurasDocumentales(),
            'tiposDocumento' => TipoDocumentoConductor::options(),
            'asistencia' => $puedeVerAsistencia
                ? $this->calendarioService->paraConductor($conductor, $this->anioPedido($request))
                : null,
            'estadisticas' => $this->estadisticas($conductor, $puedeVerAsistencia),
            'viajes' => $conductor->viajes()
                // `media` evita el N+1 de `getFirstMediaUrl()` de abajo, una
                // consulta por viaje si no se precarga.
                ->with(['media', 'clienteDelPadron:id,alias'])
                ->orderByDesc('fecha_traslado')
                ->orderByDesc('id')
                ->limit(self::VIAJES_RECIENTES)
                ->get()
                // El historial es para repasar qué manejó, no para auditar el
                // viaje: fecha, unidad, cliente, carga y la GR. Ruta, pesos y
                // destinatario se consultan en el listado de Viajes.
                ->map(fn (Viaje $viaje): array => [
                    'id' => $viaje->id,
                    'numero_gr' => $viaje->numero_gr,
                    'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                    'cliente' => $viaje->nombreCliente(),
                    'tipo_carga' => $viaje->tipo_carga->value,
                    'tipo_carga_label' => $viaje->tipo_carga->label(),
                    'placa_tracto' => $viaje->placa_tracto,
                    'placa_carreta' => $viaje->placa_carreta,
                    'archivo_url' => $viaje->getFirstMediaUrl('archivo') ?: null,
                ])
                ->all(),
        ]);
    }

    /**
     * Los números de cabecera de la ficha: cuánto lleva manejado y cómo viene
     * su mes. Los días del mes en curso salen de las marcas de asistencia, así
     * que solo se calculan para quien puede verlas —a un visor le llegan en
     * null y la ficha no muestra esas tarjetas.
     *
     * @return array{
     *     viajes_totales: int,
     *     ultimo_viaje: string|null,
     *     dias_trabajados_mes: int|null,
     *     dias_descanso_mes: int|null,
     *     faltas_mes: int|null,
     *     documentos_vigentes: int,
     *     documentos_totales: int,
     * }
     */
    private function estadisticas(Conductor $conductor, bool $puedeVerAsistencia): array
    {
        $documentos = $conductor->estadoDocumental()['documentos'];

        $porEstadoEsteMes = $puedeVerAsistencia
            ? $conductor->asistencias()
                ->whereBetween('fecha', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->get()
                ->countBy(fn (Asistencia $asistencia): string => $asistencia->estado->value)
            : null;

        return [
            'viajes_totales' => $conductor->viajes()->count(),
            'ultimo_viaje' => $conductor->viajes()->max('fecha_traslado'),
            'dias_trabajados_mes' => $porEstadoEsteMes?->get(EstadoAsistencia::Asistencia->value, 0),
            'dias_descanso_mes' => $porEstadoEsteMes?->get(EstadoAsistencia::Descanso->value, 0),
            'faltas_mes' => $porEstadoEsteMes?->get(EstadoAsistencia::Falta->value, 0),
            'documentos_vigentes' => count(array_filter(
                $documentos,
                fn (array $documento): bool => $documento['estado'] === EstadoDocumento::Vigente->value,
            )),
            'documentos_totales' => count($documentos),
        ];
    }

    /**
     * El año completo de asistencia del conductor, en su propia pantalla.
     *
     * Vive aparte de la ficha porque son doce calendarios: desplegados ahí
     * adentro empujaban el resto del expediente y dejaban las celdas
     * apretadas, que es justo donde hay que marcar.
     */
    public function asistencia(Request $request, Conductor $conductor): Response
    {
        $this->authorize('view', $conductor);
        $this->authorize('viewAny', Asistencia::class);

        return Inertia::render('conductores/asistencia', [
            'conductor' => $conductor->only(['id', 'nombres', 'apellidos', 'documento']),
            'asistencia' => $this->calendarioService->paraConductor(
                $conductor,
                $this->anioPedido($request),
            ),
        ]);
    }

    /**
     * El año pedido para la pestaña de asistencia, o el año en curso si no
     * se pidió uno válido.
     */
    private function anioPedido(Request $request): int
    {
        $anio = $request->integer('anio');

        return $anio > 0 ? $anio : (int) now()->year;
    }

    public function create(): Response
    {
        $this->authorize('create', Conductor::class);

        return Inertia::render('conductores/create', $this->datosFormulario());
    }

    public function store(StoreConductorRequest $request): RedirectResponse
    {
        $this->authorize('create', Conductor::class);

        Conductor::create($request->validated());

        return to_route('conductores.index')
            ->with('toast', ['type' => 'success', 'message' => 'Conductor registrado correctamente.']);
    }

    public function edit(Conductor $conductor): Response
    {
        $this->authorize('update', $conductor);

        return Inertia::render('conductores/edit', [
            'conductor' => $conductor,
            ...$this->datosFormulario(),
        ]);
    }

    public function update(UpdateConductorRequest $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('update', $conductor);

        $conductor->update($request->validated());

        return to_route('conductores.index', $request->query())
            ->with('toast', ['type' => 'success', 'message' => 'Conductor actualizado correctamente.']);
    }

    public function destroy(Request $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('delete', $conductor);

        $conductor->delete();

        return to_route('conductores.index', $request->query())
            ->with('toast', ['type' => 'success', 'message' => 'Conductor eliminado correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'usuarios' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }
}
