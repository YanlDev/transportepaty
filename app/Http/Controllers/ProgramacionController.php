<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\ActualizarNumerosAvisoRequest;
use App\Http\Requests\GuardarProgramacionRequest;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\AvisoDeSalida;
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
    public function __construct(private readonly AvisoDeSalida $aviso) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Programacion::class);

        $fecha = $this->fechaPedida($request);
        $guias = $this->guiasDelDia($fecha);

        $programaciones = Programacion::query()
            ->delDia($fecha->toDateString())
            ->with([
                'vehiculo:id,placa',
                'conductor:id,nombres,apellidos,telefono,telefono_alterno',
                'cliente:id,alias,razon_social',
                'avisadoPor:id,name',
            ])
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
            ->values();

        return Inertia::render('programacion/index', [
            // El aviso de operaciones va armado desde acá: es el resumen del
            // día entero, no el de una tarjeta.
            'avisoOperaciones' => [
                'whatsapp' => $this->aviso->whatsappOperaciones(),
                'mensaje' => $this->aviso->resumenDelDia($fecha->toDateString(), $programaciones, $guias),
            ],
            // La advertencia es la misma para todos: viaja una vez por
            // respuesta y no repetida en cada tarjeta.
            'advertencia' => fn (): string => $this->aviso->advertencia(),
            'fecha' => $fecha->toDateString(),
            'programaciones' => $programaciones
                ->map(fn (Programacion $programacion): array => $this->tarjeta($programacion, $fecha, $guias))
                ->all(),
            // El conteo por día de la semana en curso alimenta la tira de
            // navegación: se ve de un vistazo qué días ya tienen plan.
            'semana' => $this->semanaDe($fecha),
            // Las opciones de los formularios van en closures: el poll de la
            // página solo pide las tarjetas, la semana y el aviso, y así no
            // se recalculan cada minuto para descartarlas.
            'unidades' => fn (): array => $this->opcionesUnidades(),
            'conductores' => fn (): array => $this->opcionesConductores(),
            'clientes' => fn (): array => $this->opcionesClientes(),
            'destinosUsados' => fn (): array => $this->destinosUsados(),
            'ultimoViajePorConductor' => fn (): array => $this->ultimoViajePorConductor(),
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

    /**
     * Corrige los números a los que se avisa, sin salir de la programación:
     * el celular equivocado se descubre justo cuando hay que mandar el aviso.
     *
     * El principal y el alterno son del conductor, así que se guardan en su
     * ficha y valen para todas sus salidas; el adicional es de esta salida.
     * Un campo vacío borra el número.
     */
    public function actualizarNumeros(ActualizarNumerosAvisoRequest $request, Programacion $programacion): RedirectResponse
    {
        $this->authorize('update', $programacion);
        $this->authorize('update', $programacion->conductor);

        $programacion->conductor->update([
            'telefono' => $request->validated('telefono'),
            'telefono_alterno' => $request->validated('telefono_alterno'),
        ]);

        $programacion->update([
            'whatsapp_adicional' => $request->validated('whatsapp_adicional'),
        ]);

        return back();
    }

    /**
     * Deja constancia de que al conductor se le mandó el preaviso. Lo llama el
     * botón de WhatsApp apenas abre el chat: no prueba que el conductor lo
     * leyó —para eso está el acuse—, pero sí que la oficina avisó, y cuándo.
     */
    public function registrarAviso(Programacion $programacion): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $programacion->update([
            'aviso_enviado_at' => now(),
            'aviso_enviado_por' => request()->user()?->id,
        ]);

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
     * Las guías que salieron el día que se está viendo, por tracto. Es lo que
     * dice si una unidad programada ya partió: la GR es el registro de lo que
     * la unidad hizo de verdad.
     *
     * @return array<int, string>
     */
    private function guiasDelDia(CarbonImmutable $fecha): array
    {
        return Viaje::query()
            ->whereDate('fecha_traslado', $fecha->toDateString())
            ->whereNotNull('tracto_id')
            ->orderBy('numero_gr')
            ->pluck('numero_gr', 'tracto_id')
            ->all();
    }

    /**
     * Como en una pantalla de salidas: la unidad con GR de ese día ya salió;
     * sin GR, sigue programada mientras el día no haya pasado, y después queda
     * marcada para que alguien revise si salió sin registrar la guía o no salió.
     *
     * @param  array<int, string>  $guias
     * @return array{estado: 'despachado'|'programado'|'sin_gr', numero_gr: string|null}
     */
    private function estadoDeSalida(Programacion $programacion, CarbonImmutable $fecha, array $guias): array
    {
        $numeroGr = $guias[$programacion->vehiculo_id] ?? null;

        if ($numeroGr !== null) {
            return ['estado' => 'despachado', 'numero_gr' => $numeroGr];
        }

        return [
            'estado' => $fecha->lt(RelojOperativo::fechaDeHoy()) ? 'sin_gr' : 'programado',
            'numero_gr' => null,
        ];
    }

    /**
     * @param  array<int, string>  $guias
     * @return array<string, mixed>
     */
    private function tarjeta(Programacion $programacion, CarbonImmutable $fecha, array $guias): array
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
            'telefono' => $programacion->conductor->telefono,
            'telefono_alterno' => $programacion->conductor->telefono_alterno,
            'whatsapp_adicional' => $programacion->whatsapp_adicional,
            'precio_flete' => $programacion->precio_flete === null ? null : (float) $programacion->precio_flete,
            'precio_incluye_igv' => $programacion->precio_incluye_igv,
            ...$this->estadoDeSalida($programacion, $fecha, $guias),
            // El texto viaja armado con la tarjeta: el botón de WhatsApp solo
            // lo pone en el enlace, para que lo avisado sea siempre lo mismo.
            'destinatarios' => $this->aviso->destinatarios($programacion),
            // Los avisos a abastecimiento y a facturación, cada uno con su
            // número y su texto: el monto solo viaja en el de facturación.
            'avisos_area' => $this->aviso->avisosDeArea($programacion),
            'mensaje_aviso' => $this->aviso->mensajeParaConductor($programacion),
            'aviso_enviado_at' => $programacion->aviso_enviado_at?->toIso8601String(),
            'aviso_enviado_por' => $programacion->avisadoPor?->name,
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
            ->get(['id', 'alias', 'ruc'])
            ->map(fn (Cliente $cliente): array => [
                'id' => $cliente->id,
                'alias' => $cliente->alias,
                // Lo usa el alta express para quedarse con el cliente recién
                // creado: al volver, es por su RUC que se lo encuentra en esta
                // lista ya recargada.
                'ruc' => $cliente->ruc,
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
