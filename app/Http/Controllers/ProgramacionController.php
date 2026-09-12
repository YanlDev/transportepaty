<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\GuardarProgramacionRequest;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Qué unidades salen con carga particular cada día, para que abastecimiento
 * sepa qué preparar sin tener que preguntar por WhatsApp.
 *
 * Se carga antes de que exista la guía de remisión, así que no se deduce de
 * `viajes`: es el plan, y un plan que no termina en viaje sigue siendo un
 * dato válido. Cinco campos por tarjeta —día, unidad, conductor, cliente y
 * destino— porque el pedido fue explícito en que cargar tenía que ser rápido.
 */
class ProgramacionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Programacion::class);

        $fecha = $this->fechaPedida($request);

        $programaciones = Programacion::query()
            ->delDia($fecha->toDateString())
            ->with(['vehiculo:id,placa', 'conductor:id,nombres,apellidos', 'cliente:id,alias,razon_social'])
            // Por cliente y luego por placa: abastecimiento prepara por
            // cliente, así que las unidades del mismo cliente van juntas.
            //
            // Se ordena en PHP y no en SQL porque el criterio es el alias del
            // cliente, que vive en otra tabla y obligaría a un join solo para
            // esto. Una sola clave compuesta y no dos: `sortBy` con varias
            // claves espera pares `[campo, dirección]`, y una lista de
            // closures a secas se ignora en silencio.
            ->get()
            ->sortBy(fn (Programacion $programacion): string => $programacion->cliente->alias.'|'.$programacion->vehiculo->placa)
            ->values()
            ->map(fn (Programacion $programacion): array => $this->tarjeta($programacion))
            ->all();

        return Inertia::render('programacion/index', [
            'fecha' => $fecha->toDateString(),
            'programaciones' => $programaciones,
            // El conteo por día de la semana en curso alimenta la tira de
            // navegación: se ve de un vistazo qué días ya tienen plan.
            'semana' => $this->semanaDe($fecha),
            'unidades' => $this->opcionesUnidades(),
            'conductores' => $this->opcionesConductores(),
            'clientes' => $this->opcionesClientes(),
            'destinosUsados' => $this->destinosUsados(),
            'ultimoViajePorConductor' => $this->ultimoViajePorConductor(),
        ]);
    }

    public function store(GuardarProgramacionRequest $request): RedirectResponse
    {
        $this->authorize('create', Programacion::class);

        Programacion::query()->create($request->validated());

        return back();
    }

    public function update(GuardarProgramacionRequest $request, Programacion $programacion): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $programacion->update($request->validated());

        return back();
    }

    public function destroy(Programacion $programacion): RedirectResponse
    {
        $this->authorize('delete', $programacion);

        $programacion->delete();

        return back();
    }

    /**
     * El día que se está viendo. Sin parámetro, hoy; con uno ilegible, hoy
     * también, en vez de reventar con un 500 sobre texto arbitrario de la
     * query string.
     */
    private function fechaPedida(Request $request): CarbonImmutable
    {
        // Sin el parámetro, `value()` devuelve cadena vacía, que el patrón ya
        // descarta: no hace falta un chequeo de null aparte.
        $fecha = $request->string('fecha')->trim()->value();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            return RelojOperativo::fechaDeHoy();
        }

        return CarbonImmutable::parse($fecha);
    }

    /**
     * @return array<string, mixed>
     */
    private function tarjeta(Programacion $programacion): array
    {
        return [
            'id' => $programacion->id,
            'fecha' => $programacion->fecha->toDateString(),
            'vehiculo_id' => $programacion->vehiculo_id,
            'placa' => $programacion->vehiculo->placa,
            'conductor_id' => $programacion->conductor_id,
            'conductor' => "{$programacion->conductor->nombres} {$programacion->conductor->apellidos}",
            'cliente_id' => $programacion->cliente_id,
            // El alias y no la razón social: es el nombre corto con el que se
            // habla del cliente, y el que colorea la tarjeta.
            'cliente' => $programacion->cliente->alias,
            'destino' => $programacion->destino,
        ];
    }

    /**
     * Los siete días de la semana del día que se está viendo, con cuántas
     * unidades tiene programadas cada uno.
     *
     * @return array<int, array{fecha: string, programadas: int}>
     */
    private function semanaDe(CarbonImmutable $fecha): array
    {
        $inicio = $fecha->startOfWeek();
        $fin = $inicio->addDays(6);

        $conteos = Programacion::query()
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->selectRaw('fecha, count(*) as total')
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        return collect(range(0, 6))
            ->map(function (int $offset) use ($inicio, $conteos): array {
                $dia = $inicio->addDays($offset)->toDateString();

                return [
                    'fecha' => $dia,
                    'programadas' => (int) $conteos->get($dia, 0),
                ];
            })
            ->all();
    }

    /**
     * Solo tractos activos: es lo que se puede programar con carga.
     *
     * @return array<int, array{id: int, placa: string}>
     */
    private function opcionesUnidades(): array
    {
        return Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->orderBy('placa')
            ->get(['id', 'placa'])
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    private function opcionesConductores(): array
    {
        return Conductor::query()
            ->where('activo', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos'])
            ->map(fn (Conductor $conductor): array => [
                'id' => $conductor->id,
                'nombre' => "{$conductor->apellidos} {$conductor->nombres}",
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, alias: string}>
     */
    private function opcionesClientes(): array
    {
        return Cliente::query()
            ->where('activo', true)
            ->orderBy('alias')
            ->get(['id', 'alias'])
            ->map(fn (Cliente $cliente): array => [
                'id' => $cliente->id,
                'alias' => $cliente->alias,
            ])
            ->all();
    }

    /**
     * Con qué tracto salió cada conductor la última vez, según sus guías.
     *
     * Es lo que hace rápida la carga: elegir el conductor deja la unidad
     * puesta, porque un chofer maneja casi siempre el mismo tracto. Se manda
     * con la página y no por una consulta al elegir para que el prellenado
     * sea instantáneo — son sesenta filas, no justifica una ida al servidor.
     *
     * Sale de los viajes y no de las programaciones anteriores porque la guía
     * es el registro de lo que la unidad hizo de verdad; una programación
     * pudo no haberse cumplido.
     *
     * @return array<int, array{vehiculo_id: int, placa: string, fecha: string}>
     */
    private function ultimoViajePorConductor(): array
    {
        // Se ordena y se queda con el primero de cada conductor en PHP en vez
        // de resolverlo con `DISTINCT ON`, que es solo de Postgres y dejaría
        // la consulta sin correr en los tests (SQLite). Con el volumen de
        // viajes de la operación el costo es despreciable.
        return Viaje::query()
            ->whereNotNull('conductor_id')
            ->whereNotNull('tracto_id')
            ->with('tracto:id,placa')
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('numero_gr')
            ->get(['id', 'conductor_id', 'tracto_id', 'fecha_traslado'])
            ->unique('conductor_id')
            ->reduce(function (array $mapa, Viaje $viaje): array {
                $conductorId = $viaje->conductor_id;
                $tractoId = $viaje->tracto_id;

                // La consulta ya los excluye; el chequeo está para que el
                // mapa tenga el tipo que declara el docblock, sin castear.
                if ($conductorId === null || $tractoId === null) {
                    return $mapa;
                }

                $mapa[$conductorId] = [
                    'vehiculo_id' => $tractoId,
                    'placa' => $viaje->tracto->placa,
                    'fecha' => $viaje->fecha_traslado->toDateString(),
                ];

                return $mapa;
            }, []);
    }

    /**
     * Los destinos ya tipeados, para autocompletar el campo libre y que el
     * mismo lugar no termine escrito de tres formas distintas.
     *
     * @return array<int, string>
     */
    private function destinosUsados(): array
    {
        return Programacion::query()
            ->select('destino')
            ->distinct()
            ->orderBy('destino')
            ->pluck('destino')
            ->all();
    }
}
