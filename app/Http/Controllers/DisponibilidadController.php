<?php

namespace App\Http\Controllers;

use App\Enums\Cliente;
use App\Enums\EstadoCarga;
use App\Enums\EstadoVehiculo;
use App\Enums\FaseCiclo;
use App\Enums\TipoCaja;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Http\Requests\ActualizarCeldaEstadoUnidadRequest;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use App\Services\Alerta;
use App\Services\ValidadorEstadoUnidad;
use App\Services\ValidadorTransicion;
use Illuminate\Database\Eloquent\Collection as EstadosCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La disponibilidad del día: dónde está cada unidad y qué lleva. Trabaja sobre
 * el estado canónico, que es la fuente de verdad de la operación —el Excel es
 * apenas una de las formas de alimentarla— así que todo lo que se ve acá se
 * puede escribir a mano sin depender de ningún archivo.
 */
class DisponibilidadController extends Controller
{
    /**
     * Lo que se borra al vaciar un día: la carga y la ruta reportadas, nunca
     * la unidad, la carreta ni el conductor. `estado_carga` y `fase` van
     * incluidos porque los deduce `tipo_carga`; dejarlos sueltos contradiría
     * a la carga que se acaba de borrar.
     *
     * @var list<string>
     */
    private const CAMPOS_DE_CARGA = [
        'tipo_carga',
        'estado_carga',
        'cliente',
        'fase',
        'origen',
        'destino',
        'ubicacion',
    ];

    /**
     * Relaciones que necesitan la vista y los validadores. Sin la asignación
     * vigente del tracto, comprobar el conductor costaría una consulta por fila.
     *
     * @var list<string>
     */
    private const RELACIONES = [
        'tracto:id,placa,caja',
        'tracto.asignacionVigenteComoTracto',
        'tracto.asignacionVigenteComoTracto.conductor:id,nombres,apellidos',
        'tracto.asignacionVigenteComoTracto.carreta:id,placa',
        'carreta:id,placa',
        'conductor:id,nombres,apellidos',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EstadoUnidad::class);

        $fecha = $this->fechaPedida($request);
        $caja = $this->cajaPedida($request);

        $tractos = Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->when($caja !== null, fn ($query) => $query->where('caja', $caja))
            ->with([
                'asignacionVigenteComoTracto',
                'asignacionVigenteComoTracto.conductor:id,nombres,apellidos',
                'asignacionVigenteComoTracto.carreta:id,placa',
            ])
            ->orderBy('placa')
            ->get(['id', 'placa', 'caja']);

        $estados = EstadoUnidad::query()
            ->with(self::RELACIONES)
            ->delDia($fecha)
            ->whereIn('tracto_id', $tractos->pluck('id'))
            ->get()
            ->keyBy('tracto_id');

        $anteriores = EstadoUnidad::ultimosAntesDe($fecha, $estados->keys()->all());

        $porFila = new ValidadorEstadoUnidad;
        $porTransicion = new ValidadorTransicion;

        $filas = $tractos->map(function (Vehiculo $tracto) use ($estados, $anteriores, $porFila, $porTransicion): array {
            $estado = $estados->get($tracto->id);

            if ($estado === null) {
                return $this->comoFilaVacia($tracto);
            }

            $alertas = [
                ...$porFila->validar($estado),
                ...$porTransicion->validar($estado, $anteriores->get($tracto->id)),
            ];

            return $this->comoFila($estado, $alertas);
        });

        return Inertia::render('disponibilidad/index', [
            'fecha' => $fecha,
            'caja' => $caja?->value,
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
            'arrastrables' => $this->arrastrables($fecha, $tractos, $estados),
            'opciones' => $this->opciones(),
        ]);
    }

    public function actualizarCelda(ActualizarCeldaEstadoUnidadRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        abort_unless($vehiculo->tipo === TipoVehiculo::Tracto, 404);

        $campo = $request->string('campo')->value();

        // firstOrNew hidrata la fila completa si ya existía: editar una celda
        // no pisa las demás, a diferencia de un formulario completo a ciegas.
        $estado = EstadoUnidad::query()->firstOrNew([
            'tracto_id' => $vehiculo->id,
            'fecha' => $request->date('fecha')->toDateString(),
        ]);

        $estado->setAttribute($campo, $request->input('valor'));

        if (in_array($campo, EstadoUnidad::CAMPOS_CONFIRMABLES, true)) {
            $estado->confirmar([$campo]);
        }

        $estado->save();

        return back();
    }

    /**
     * Vacía la carga y la ruta del día, sin tocar la unidad, la carreta ni el
     * conductor: son datos de asignación, no del reporte de ese día, y
     * perderlos obligaría a volver a teclearlos por un error de carga.
     */
    public function destroy(EstadoUnidad $estado): RedirectResponse
    {
        $this->authorize('delete', $estado);

        $fecha = $estado->fecha->toDateString();
        $placa = $estado->tracto->placa;

        foreach (self::CAMPOS_DE_CARGA as $campo) {
            $estado->setAttribute($campo, null);
        }

        $estado->origenes = collect($estado->origenes ?? [])
            ->except(self::CAMPOS_DE_CARGA)
            ->all();

        $estado->save();

        return to_route('disponibilidad.index', ['fecha' => $fecha])
            ->with('toast', [
                'type' => 'success',
                'message' => "Se vació la carga y la ruta de {$placa}.",
            ]);
    }

    /**
     * Arrastra al día el último estado conocido de las unidades que todavía no
     * figuran. La mayoría de las unidades no cambia de un día para otro —un
     * tramo dura días— así que sin esto operar a mano sería teclear la flota
     * entera cada mañana.
     *
     * Lo arrastrado se marca como deducido, nunca como confirmado: es una
     * suposición del sistema y tiene que verse como tal hasta que la revises.
     */
    public function arrastrar(Request $request): RedirectResponse
    {
        $this->authorize('create', EstadoUnidad::class);

        $fecha = $this->fechaPedida($request);

        $tractoIds = Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->pluck('id')
            ->all();

        $yaRegistrados = EstadoUnidad::query()->delDia($fecha)->whereIn('tracto_id', $tractoIds)->pluck('tracto_id')->all();
        $anteriores = EstadoUnidad::ultimosAntesDe($fecha, $tractoIds);

        $copiados = 0;

        foreach ($anteriores as $anterior) {
            if (in_array($anterior->tracto_id, $yaRegistrados, true)) {
                continue;
            }

            $this->arrastrarUno($anterior, $fecha);
            $copiados++;
        }

        return to_route('disponibilidad.index', ['fecha' => $fecha])
            ->with('toast', [
                'type' => $copiados > 0 ? 'success' : 'info',
                'message' => $copiados > 0
                    ? "Se arrastraron {$copiados} unidades del último día registrado."
                    : 'No había unidades pendientes de arrastrar.',
            ]);
    }

    /**
     * Copia un estado al día indicado. La procedencia no se hereda: lo que ayer
     * estaba confirmado hoy vuelve a ser una suposición, porque es un día nuevo
     * y nadie lo ha mirado todavía.
     */
    private function arrastrarUno(EstadoUnidad $anterior, string $fecha): void
    {
        EstadoUnidad::query()->create([
            'fecha' => $fecha,
            'tracto_id' => $anterior->tracto_id,
            'carreta_id' => $anterior->carreta_id,
            'conductor_id' => $anterior->conductor_id,
            'tipo_carga' => $anterior->tipo_carga,
            'origen' => $anterior->origen,
            'destino' => $anterior->destino,
            'ubicacion' => $anterior->ubicacion,
            'fecha_disponible' => $anterior->fecha_disponible,
        ]);
    }

    /**
     * Cuántas unidades quedarían por arrastrar, para que el botón diga qué va a
     * hacer antes de que lo pulses. Solo cuenta tractos activos: son los únicos
     * que la hoja muestra y sobre los que tiene sentido arrastrar algo.
     *
     * @param  Collection<int, Vehiculo>  $tractos
     * @param  EstadosCollection<int, EstadoUnidad>  $estados
     */
    private function arrastrables(string $fecha, Collection $tractos, EstadosCollection $estados): int
    {
        return EstadoUnidad::ultimosAntesDe($fecha, $tractos->pluck('id')->all())
            ->reject(fn (EstadoUnidad $anterior): bool => $estados->has($anterior->tracto_id))
            ->count();
    }

    /**
     * Fecha consultada, o el día de hoy si no se pidió ninguna válida.
     */
    private function fechaPedida(Request $request): string
    {
        $fecha = $request->string('fecha')->value();

        try {
            return $fecha === '' ? now()->toDateString() : Carbon::parse($fecha)->toDateString();
        } catch (\Exception) {
            return now()->toDateString();
        }
    }

    /**
     * Filtro de caja pedido (mecánica/automática), o null para ver toda la
     * flota. Solo las automáticas suben a mina, así que separarlas es lo que
     * deja ver de un vistazo cuáles pueden ir.
     */
    private function cajaPedida(Request $request): ?TipoCaja
    {
        return TipoCaja::tryFrom($request->string('caja')->value());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{total: int, reportadas: int, imposibles: int, improbables: int}
     */
    private function resumen(Collection $filas): array
    {
        return [
            'total' => $filas->count(),
            'reportadas' => $filas->filter(fn (array $fila): bool => $fila['id'] !== null)->count(),
            'imposibles' => $filas->sum(fn (array $fila): int => $fila['imposibles']),
            'improbables' => $filas->sum(fn (array $fila): int => $fila['improbables']),
        ];
    }

    /**
     * Catálogos que necesitan los desplegables de edición de celda.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'carretas' => Vehiculo::query()
                ->where('tipo', TipoVehiculo::Carreta)
                ->orderBy('placa')
                ->get(['id', 'placa'])
                ->map(fn (Vehiculo $carreta): array => [
                    'value' => (string) $carreta->id,
                    'label' => $carreta->placa,
                ]),
            'conductores' => Conductor::query()
                ->where('activo', true)
                ->orderBy('nombres')
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos'])
                ->map(fn (Conductor $conductor): array => [
                    'value' => (string) $conductor->id,
                    'label' => $conductor->nombre_completo,
                ]),
            'cargas' => TipoCarga::options(),
            'estados_carga' => EstadoCarga::options(),
            'clientes' => Cliente::options(),
            'fases' => FaseCiclo::options(),
            'cajas' => TipoCaja::options(),
        ];
    }

    /**
     * @param  list<Alerta>  $alertas
     * @return array<string, mixed>
     */
    private function comoFila(EstadoUnidad $estado, array $alertas): array
    {
        return [
            'id' => $estado->id,
            'tracto' => [
                'id' => $estado->tracto->id,
                'placa' => $estado->tracto->placa,
                'caja' => $estado->tracto->caja?->value,
            ],
            'carreta' => $estado->carreta === null ? null : [
                'id' => $estado->carreta->id,
                'placa' => $estado->carreta->placa,
            ],
            'conductor' => $estado->conductor === null ? null : [
                'id' => $estado->conductor->id,
                'nombre_completo' => $estado->conductor->nombre_completo,
            ],
            'tipo_carga' => $estado->tipo_carga?->value,
            'tipo_carga_label' => $estado->tipo_carga?->label(),
            'estado_carga' => $estado->estado_carga?->value,
            'estado_carga_label' => $estado->estado_carga?->label(),
            'cliente' => $estado->cliente?->value,
            'cliente_label' => $estado->cliente?->label(),
            'fase' => $estado->fase?->value,
            'fase_label' => $estado->fase?->label(),
            'origen' => $estado->origen,
            'destino' => $estado->destino,
            'ubicacion' => $estado->ubicacion,
            'observaciones' => $estado->observaciones,
            'proximas_paradas' => $estado->proximas_paradas,
            'fecha_disponible' => $estado->fecha_disponible?->toDateString(),
            'origenes' => $estado->origenes ?? [],
            'asignacion_vigente' => $this->comoAsignacion($estado->tracto->asignacionVigenteComoTracto),
            'alertas' => array_map(fn (Alerta $alerta): array => $alerta->toArray(), $alertas),
            'imposibles' => count(array_filter(
                $alertas,
                fn (Alerta $alerta): bool => $alerta->nivel()->prioridad() === 0,
            )),
            'improbables' => count(array_filter(
                $alertas,
                fn (Alerta $alerta): bool => $alerta->nivel()->prioridad() === 1,
            )),
        ];
    }

    /**
     * La fila de un tracto activo que todavía no reportó nada este día: mismo
     * formato que `comoFila()` pero en blanco, para que la hoja siempre tenga
     * las mismas filas que el Excel real.
     *
     * @return array<string, mixed>
     */
    private function comoFilaVacia(Vehiculo $tracto): array
    {
        return [
            'id' => null,
            'tracto' => ['id' => $tracto->id, 'placa' => $tracto->placa, 'caja' => $tracto->caja?->value],
            'carreta' => null,
            'conductor' => null,
            'tipo_carga' => null,
            'tipo_carga_label' => null,
            'estado_carga' => null,
            'estado_carga_label' => null,
            'cliente' => null,
            'cliente_label' => null,
            'fase' => null,
            'fase_label' => null,
            'origen' => null,
            'destino' => null,
            'ubicacion' => null,
            'observaciones' => null,
            'proximas_paradas' => null,
            'fecha_disponible' => null,
            'origenes' => [],
            'asignacion_vigente' => $this->comoAsignacion($tracto->asignacionVigenteComoTracto),
            'alertas' => [],
            'imposibles' => 0,
            'improbables' => 0,
        ];
    }

    /**
     * @return array{conductor: string, carreta: string|null}|null
     */
    private function comoAsignacion(?Asignacion $asignacion): ?array
    {
        return $asignacion === null ? null : [
            'conductor' => $asignacion->conductor->nombre_completo,
            'carreta' => $asignacion->carreta?->placa,
        ];
    }
}
