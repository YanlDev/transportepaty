<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\ConsultarGuiaRemitenteRequest;
use App\Http\Requests\EmitirGreRequest;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\Sunat\ClienteGreSunat;
use App\Services\Sunat\EmisionEnDuda;
use App\Services\Sunat\EmisionGre;
use App\Services\Sunat\EmisionRechazada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * La pantalla para armar una GR-transportista desde Transpaty: se elige la
 * GR-remitente, la unidad y el conductor, y SUNAT confirma cada dato antes de
 * emitir. Las consultas van por {@see ClienteGreSunat} y la emisión por
 * {@see EmisionGre}, las dos con la sesión SOL de Paty.
 */
class EmisionGreController extends Controller
{
    /** Razón social de un emisor de GR-remitente, aprendida de SUNAT. */
    private const CLAVE_NOMBRE_REMITENTE = 'sunat.razon-social.';

    public function __construct(
        private readonly ClienteGreSunat $sunat,
        private readonly EmisionGre $emision,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Viaje::class);

        return Inertia::render('viajes/emitir', [
            'tractos' => $this->placas(TipoVehiculo::Tracto),
            'carretas' => $this->placas(TipoVehiculo::Carreta),
            'conductores' => Conductor::query()
                ->where('activo', true)
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'documento', 'licencia'])
                ->map(fn (Conductor $conductor): array => [
                    'id' => $conductor->id,
                    'nombre' => "{$conductor->apellidos} {$conductor->nombres}",
                    'documento' => $conductor->documento,
                    'licencia' => $conductor->licencia,
                ]),
            'clientes' => Cliente::query()
                ->where('activo', true)
                ->orderBy('alias')
                ->get(['ruc', 'alias']),
            'sunatConfigurado' => filled(config('services.sunat_sol.clave')),
            'puedeEmitir' => request()->user()?->can('emitir', Viaje::class) ?? false,
            'rucPaty' => (string) config('services.sunat_sol.ruc'),
            'ultimos' => $this->ultimasCombinaciones(),
            'remitentes' => $this->remitentesFrecuentes(),
        ]);
    }

    /**
     * Emite la GR-transportista en SUNAT y registra el viaje. Responde con el
     * número emitido; si SUNAT la rechaza, con su motivo (no se emitió nada);
     * si queda en duda, lo dice claro para que nadie la vuelva a mandar a
     * ciegas.
     */
    public function emitir(EmitirGreRequest $request): JsonResponse
    {
        $this->authorize('emitir', Viaje::class);

        $guias = array_values(array_map(fn (array $guia): array => [
            'ruc' => (string) $guia['ruc'],
            'serie' => Str::upper((string) $guia['serie']),
            'numero' => (int) $guia['numero'],
        ], $request->array('guias')));
        $pagador = $request->string('pagador')->value();

        try {
            $resultado = $this->emision->emitir(
                $guias,
                Vehiculo::query()->findOrFail($request->integer('tracto_id')),
                $request->filled('carreta_id') ? Vehiculo::query()->findOrFail($request->integer('carreta_id')) : null,
                Conductor::query()->findOrFail($request->integer('conductor_id')),
                $request->string('fecha_traslado')->value(),
                $pagador,
                $request->filled('ruc_pagador') && $pagador !== EmisionGre::PAGADOR_REMITENTE ? $request->string('ruc_pagador')->value() : null,
                array_filter([
                    $request->integer('tracto_id') => $request->string('tuce_tracto')->trim()->value(),
                    $request->integer('carreta_id') => $request->string('tuce_carreta')->trim()->value(),
                ]),
                $request->filled('ruc_subcontratador') ? $request->string('ruc_subcontratador')->value() : null,
            );
        } catch (EmisionEnDuda $duda) {
            return response()->json([
                'estado' => 'en_duda',
                'mensaje' => $duda->getMessage().' La GR pudo haberse emitido: revisa «Consulta de GRE» en SOL antes de volver a intentar.',
            ], 504);
        } catch (EmisionRechazada $rechazo) {
            return response()->json(['estado' => 'rechazada', 'mensaje' => 'SUNAT no emitió la GR: '.$rechazo->getMessage()], 422);
        } catch (RuntimeException $error) {
            return response()->json(['estado' => 'no_enviada', 'mensaje' => $error->getMessage()], 422);
        }

        return response()->json([
            'estado' => 'emitida',
            'numeroGr' => $resultado['numero_gr'],
            'viajeRegistrado' => $resultado['viaje'] !== null,
        ]);
    }

    /**
     * La GR-remitente según SUNAT, resumida para la vista previa, más los
     * avisos que frenarían la emisión (ya tiene GR-transportista, es de otro
     * transportista, no está vigente).
     */
    public function guia(ConsultarGuiaRemitenteRequest $request): JsonResponse
    {
        $this->authorize('create', Viaje::class);

        $ruc = $request->string('ruc')->value();
        $serie = $request->string('serie')->upper()->value();
        $numero = $request->integer('numero');

        try {
            $guia = $this->sunat->guiaRemitente($ruc, $serie, $numero);
        } catch (RuntimeException $error) {
            return response()->json(['mensaje' => $error->getMessage()], 502);
        }

        if ($guia === null) {
            return response()->json(['mensaje' => "SUNAT no encontró la GR-remitente {$serie}-{$numero} del RUC {$ruc}."], 404);
        }

        $datos = $guia['datos'];
        $transportista = (string) data_get($datos, 'traslado.transportista.numDocIdentidad', '');
        $fechaTraslado = data_get($datos, 'traslado.fecInicioTraslado');
        $yaEmitida = $this->emision->grTransportistaExistente($ruc, $serie, $numero);

        $avisos = array_values(array_filter([
            $yaEmitida !== null ? "Ya tiene GR-transportista en Transpaty: {$yaEmitida}." : null,
            $transportista !== '' && $transportista !== config('services.sunat_sol.ruc')
                ? "La GR-remitente consigna a otro transportista (RUC {$transportista})."
                : null,
            data_get($datos, 'codEstado') !== null && data_get($datos, 'codEstado') !== '01'
                ? 'La GR-remitente no está vigente en SUNAT ('.data_get($datos, 'desEstado', 'estado '.data_get($datos, 'codEstado')).').'
                : null,
            is_string($fechaTraslado) && Carbon::parse($fechaTraslado)->lt(now()->subDays(30))
                ? 'La GR-remitente es de hace más de 30 días ('.Carbon::parse($fechaTraslado)->format('d/m/Y').').'
                : null,
        ]));

        // La razón social del emisor se aprende acá: los que no son clientes
        // de Paty (Ajeper, Caral…) no están en ningún padrón, y así la lista
        // de remitentes los muestra con nombre la próxima vez.
        if (filled(data_get($datos, 'emisor.desNombre'))) {
            Cache::forever(self::CLAVE_NOMBRE_REMITENTE.$ruc, (string) data_get($datos, 'emisor.desNombre'));
        }

        return response()->json([
            'serie' => $serie,
            'numero' => $numero,
            'ruc' => $ruc,
            'completa' => $guia['completa'],
            'remitente' => data_get($datos, 'emisor.desNombre'),
            'destinatario' => data_get($datos, 'receptor.desNombre'),
            'destinatarioRuc' => data_get($datos, 'receptor.numDocIdentidad'),
            'partida' => $this->direccion(data_get($datos, 'traslado.partida.direccion')),
            'llegada' => $this->direccion(data_get($datos, 'traslado.llegada.direccion')),
            'peso' => data_get($datos, 'traslado.numPesoBruto'),
            'unidadPeso' => data_get($datos, 'traslado.codUnidadMedidaPb'),
            'bultos' => data_get($datos, 'traslado.numBultosPallets'),
            'motivo' => data_get($datos, 'traslado.desMotivoTraslado'),
            'fechaTraslado' => is_string($fechaTraslado) ? Carbon::parse($fechaTraslado)->toDateString() : null,
            'transportistaRuc' => $transportista ?: null,
            'avisos' => $avisos,
        ]);
    }

    /**
     * Vuelve a intentar registrar como viaje una GR ya emitida cuyo PDF no
     * llegó al emitir. Solo lee de SUNAT: no emite nada.
     */
    public function registrar(Request $request): JsonResponse
    {
        $this->authorize('emitir', Viaje::class);

        $numeroGr = (string) $request->validate(['numero_gr' => ['required', 'string', 'regex:/^[A-Za-z0-9]{4}-\d{1,8}$/']])['numero_gr'];

        try {
            $viaje = $this->emision->registrarEmitida($numeroGr);
        } catch (RuntimeException $error) {
            return response()->json(['mensaje' => $error->getMessage()], 502);
        }

        return response()->json(['viajeRegistrado' => $viaje !== null]);
    }

    /**
     * El TUCE que irá en la GR para esa placa y de dónde sale (la ficha, el
     * MTC o, sin ninguno, el RUC de Paty): ver {@see EmisionGre::tuce()}.
     */
    public function vehiculo(Vehiculo $vehiculo): JsonResponse
    {
        $this->authorize('create', Viaje::class);

        try {
            $tuce = $this->emision->tuce($vehiculo);
        } catch (RuntimeException $error) {
            return response()->json(['mensaje' => $error->getMessage()], 502);
        }

        return response()->json(['placa' => $vehiculo->placa] + Arr::only($tuce, ['numero', 'origen', 'vence', 'placaEnSunat']));
    }

    /**
     * El DNI contra RENIEC (vía SUNAT): el nombre que devuelve tiene que ser
     * el del conductor elegido. La licencia se consulta aparte y solo informa:
     * a septiembre de 2026 la consulta del MTC no encuentra ninguna licencia,
     * ni con letra ni sin ella, y SOL deja emitir igual.
     */
    public function conductor(Conductor $conductor): JsonResponse
    {
        $this->authorize('create', Viaje::class);

        try {
            $persona = $this->sunat->persona($conductor->documento);
            $licencia = filled($conductor->licencia)
                ? $this->sunat->licencia((string) $conductor->licencia)
                : ['encontrada' => false, 'mensaje' => 'El conductor no tiene licencia registrada en Transpaty.'];
        } catch (RuntimeException $error) {
            return response()->json(['mensaje' => $error->getMessage()], 502);
        }

        $nombreReniec = $persona === null ? null : trim(implode(' ', array_filter([
            $persona['apePaterno'] ?? null,
            $persona['apeMaterno'] ?? null,
            $persona['nomPerNat'] ?? null,
        ])));

        return response()->json([
            'dni' => [
                'encontrado' => filled($nombreReniec),
                'nombre' => $nombreReniec ?: null,
                'coincide' => filled($nombreReniec)
                    && $this->mismasPalabras((string) $nombreReniec, "{$conductor->apellidos} {$conductor->nombres}"),
            ],
            'licencia' => Arr::only($licencia, ['encontrada', 'mensaje']),
        ]);
    }

    /**
     * Si dos nombres son el mismo sin mirar orden, mayúsculas ni tildes:
     * RENIEC da «MAMANI MASCO ADOLFO» y el padrón «Adolfo Mamani Masco».
     */
    private function mismasPalabras(string $uno, string $otro): bool
    {
        $palabras = fn (string $nombre): array => collect(preg_split('/\s+/', Str::upper(Str::ascii($nombre))) ?: [])
            ->filter()
            ->sort()
            ->values()
            ->all();

        return $palabras($uno) === $palabras($otro);
    }

    private function direccion(mixed $direccion): ?string
    {
        if (! is_array($direccion)) {
            return null;
        }

        $lugar = collect([$direccion['desDistrito'] ?? null, $direccion['desProvincia'] ?? null, $direccion['desDepartamento'] ?? null])
            ->map(fn (?string $parte): string => trim((string) $parte))
            ->filter()
            ->implode(', ');

        return trim(preg_replace('/\s+/', ' ', (string) ($direccion['desDireccion'] ?? '')).($lugar !== '' ? " ({$lugar})" : ''));
    }

    /**
     * Quiénes emiten las GR-remitente que transporta Paty, del más frecuente
     * al menos, con quién contrata a Paty en esos viajes. No siempre es el
     * mismo: Ajeper y Embotelladora Caral emiten la guía pero contrata Crisar
     * (subcontratación), Mur-Wy a veces llega por MAG Logística. Sale de las
     * GR del último año, no de un padrón que haya que mantener.
     *
     * @return list<array{ruc: string, nombre: string|null, viajes: int, contratante: array{ruc: string, nombre: string}|null}>
     */
    private function remitentesFrecuentes(): array
    {
        $padron = Cliente::query()->pluck('alias', 'ruc');
        $conteo = [];
        $nombres = [];
        $contratantes = [];

        $viajes = Viaje::query()
            ->whereNotNull('guias_remitente')
            ->where('fecha_traslado', '>=', now()->subYear()->toDateString())
            ->get(['cliente', 'cliente_ruc', 'guias_remitente']);

        foreach ($viajes as $viaje) {
            foreach ($viaje->guias_remitente ?? [] as $guia) {
                $ruc = $guia['ruc'];
                $conteo[$ruc] = ($conteo[$ruc] ?? 0) + 1;

                if ($viaje->cliente_ruc === $ruc) {
                    $nombres[$ruc] ??= $viaje->cliente;
                } elseif (filled($viaje->cliente_ruc)) {
                    $clave = (string) $viaje->cliente_ruc;
                    $contratantes[$ruc][$clave] = ($contratantes[$ruc][$clave] ?? 0) + 1;
                    $nombres[$clave] ??= $viaje->cliente;
                }
            }
        }

        arsort($conteo);

        return array_values(collect($conteo)
            ->take(60)
            ->map(function (int $viajesDelRemitente, string|int $ruc) use ($padron, $nombres, $contratantes): array {
                $ruc = (string) $ruc;
                $nombre = fn (string $unRuc): ?string => $padron[$unRuc] ?? Cache::get(self::CLAVE_NOMBRE_REMITENTE.$unRuc) ?? $nombres[$unRuc] ?? null;
                $contratante = null;

                // Contratante habitual: el que aparece en más de la mitad de
                // los viajes de ese remitente. Si es mitad y mitad, no se
                // propone nada: no hay un «de siempre».
                if (isset($contratantes[$ruc])) {
                    arsort($contratantes[$ruc]);
                    $rucContratante = (string) array_key_first($contratantes[$ruc]);

                    if ($contratantes[$ruc][$rucContratante] * 2 > $viajesDelRemitente) {
                        $contratante = ['ruc' => $rucContratante, 'nombre' => $nombre($rucContratante) ?? $rucContratante];
                    }
                }

                return [
                    'ruc' => $ruc,
                    'nombre' => $nombre($ruc),
                    'viajes' => $viajesDelRemitente,
                    'contratante' => $contratante,
                ];
            })
            ->all());
    }

    /**
     * La última unidad y el último conductor con que salió cada tracto y cada
     * conductor, para autollenar la pantalla: al elegir el tracto se proponen
     * su carreta y su chofer de siempre, y al revés. Se miran los viajes de
     * los últimos 120 días (los anulados no cuentan: el filtro global los
     * saca) y gana el más reciente.
     *
     * @return array{porTracto: array<int, array{carreta: int|null, conductor: int|null}>, porConductor: array<int, array{tracto: int, carreta: int|null}>}
     */
    private function ultimasCombinaciones(): array
    {
        $porTracto = [];
        $porConductor = [];

        $viajes = Viaje::query()
            ->whereNotNull('tracto_id')
            ->where('fecha_traslado', '>=', now()->subDays(120)->toDateString())
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('id')
            ->get(['tracto_id', 'carreta_id', 'conductor_id']);

        foreach ($viajes as $viaje) {
            $tracto = $viaje->tracto_id;

            if ($tracto === null) {
                continue;
            }

            $porTracto[$tracto] ??= ['carreta' => $viaje->carreta_id, 'conductor' => $viaje->conductor_id];

            if ($viaje->conductor_id !== null) {
                $porConductor[$viaje->conductor_id] ??= ['tracto' => $tracto, 'carreta' => $viaje->carreta_id];
            }
        }

        return ['porTracto' => $porTracto, 'porConductor' => $porConductor];
    }

    /**
     * @return Collection<int, array{id: int, placa: string}>
     */
    private function placas(TipoVehiculo $tipo): Collection
    {
        return Vehiculo::query()
            ->where('tipo', $tipo)
            ->whereIn('estado', EstadoVehiculo::asignables())
            ->orderBy('placa')
            ->get(['id', 'placa'])
            ->map(fn (Vehiculo $vehiculo): array => ['id' => $vehiculo->id, 'placa' => $vehiculo->placa]);
    }
}
