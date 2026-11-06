<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarVueltaRequest;
use App\Http\Requests\RegistrarLlegadaRequest;
use App\Models\AreaAviso;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\DestinoParticular;
use App\Models\EnvioWhatsapp;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\VarianteRuta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AlertasDelTicket;
use App\Services\AsignadorVueltas;
use App\Services\AvisoDeVuelta;
use App\Services\AvisosPorWhatsapp;
use App\Services\GastosDelTicket;
use App\Services\LiquidacionTicket;
use App\Services\OdometroDeUnidad;
use App\Services\PasosDelTicket;
use App\Services\ProgramadorVueltas;
use App\Services\RelojOperativo;
use App\Services\WhatsappServicio;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las vueltas de la flota: en qué anda cada unidad y qué tiene programado
 * después (la portada), la historia de una unidad con sus GR, y el
 * formulario para programar la vuelta y corregirla en el camino.
 */
class VueltaController extends Controller
{
    /**
     * Hasta cuántos días después de volver a la base se muestra una unidad
     * como disponible: más que eso es una unidad parada, no una por programar.
     */
    private const DIAS_EN_BASE_EN_TABLERO = 30;

    public function __construct(
        private readonly ProgramadorVueltas $programador,
        private readonly AvisoDeVuelta $aviso,
        private readonly AvisosPorWhatsapp $avisos,
        private readonly GastosDelTicket $gastos,
        private readonly PasosDelTicket $pasos,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        return Inertia::render('vueltas/index', [
            // Hoy: la fecha de salida que se propone al programar.
            'fecha' => RelojOperativo::fechaDeHoy()->toDateString(),
            'tablero' => $this->tablero($request),
            // Las GR que esperan que alguien las ponga en un ticket.
            'guiasPorAsignar' => fn (): int => Viaje::query()->whereNull('parada_vuelta_id')->whereNull('sin_ticket_at')->count(),
            ...$this->opcionesDelFormulario(),
        ]);
    }

    public function unidad(Request $request, Vehiculo $vehiculo): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        $vueltas = Vuelta::query()
            ->where('tracto_id', $vehiculo->id)
            ->with($this->relaciones())->withExists(['abastecimientos', 'viaticos'])
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('vueltas/unidad', [
            'tracto' => ['id' => $vehiculo->id, 'placa' => $vehiculo->placa],
            'vueltas' => $vueltas->map(fn (Vuelta $vuelta): array => $this->vuelta($vuelta, $request))->all(),
            ...$this->opcionesDelFormulario(),
        ]);
    }

    /**
     * El expediente del ticket: todo lo que le pasó, paso a paso, en una
     * sola pantalla. Cada área sigue trabajando en su bandeja; acá se ve el
     * conjunto y se salta a donde haga falta.
     */
    public function show(Request $request, Vuelta $vuelta, WhatsappServicio $whatsapp, LiquidacionTicket $liquidacion): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        $vuelta->load([...$this->relaciones(), 'envios'])->loadExists(['abastecimientos', 'viaticos']);

        $gastos = $this->gastos->de(collect([$vuelta]))[$vuelta->id];

        return Inertia::render('vueltas/show', [
            'ticket' => $this->fila($vuelta, $vuelta->etapa(), $request),
            ...$this->pasos->de($vuelta, $gastos),
            'gastos' => $gastos,
            'liquidacion' => $liquidacion->de($vuelta, $gastos),
            'guias' => $this->guias($vuelta, $request),
            'aviso' => $this->avisoDe($vuelta),
            'puedeAvisar' => $request->user()?->can('avisar', $vuelta) ?? false,
            'whatsappConectado' => fn (): bool => $whatsapp->conectado(),
            ...$this->opcionesDelFormulario(),
        ]);
    }

    public function store(GuardarVueltaRequest $request, AlertasDelTicket $alertas): RedirectResponse
    {
        $this->authorize('create', Vuelta::class);

        $vuelta = $this->programador->programar($request->datos(), $request->user());
        $alertas->programado($vuelta, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Vuelta {$vuelta->codigo} programada."]);
    }

    public function update(GuardarVueltaRequest $request, Vuelta $vuelta): RedirectResponse
    {
        $this->authorize('update', $vuelta);

        $this->programador->actualizar($vuelta, $request->datos(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Vuelta {$vuelta->codigo} actualizada."]);
    }

    /**
     * Liquidar y cerrar: solo con el ticket completo (llegó a la base y cada
     * combustible tiene su despacho). Cerrado, ya no recibe nada.
     */
    public function cerrar(Request $request, Vuelta $vuelta, AsignadorVueltas $asignador, AlertasDelTicket $alertas, LiquidacionTicket $liquidacion): RedirectResponse
    {
        $this->authorize('cerrar', $vuelta);

        $vuelta->load($this->relaciones());
        $pendientes = $liquidacion->de($vuelta, $this->gastos->de(collect([$vuelta]))[$vuelta->id])['pendientes'];

        if ($pendientes !== []) {
            return back()->with('toast', ['type' => 'error', 'message' => "El ticket {$vuelta->codigo} todavía no se puede cerrar: ".implode(' ', $pendientes)]);
        }

        $asignador->cerrar($vuelta);
        $alertas->cerrado($vuelta, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Ticket {$vuelta->codigo} liquidado y cerrado."]);
    }

    /**
     * Que la unidad volvió a la base, con el día y el kilometraje: desde ahí
     * el ticket está «en base» y se puede programar el siguiente.
     */
    public function registrarLlegada(RegistrarLlegadaRequest $request, Vuelta $vuelta, OdometroDeUnidad $odometro): RedirectResponse
    {
        $this->authorize('registrarLlegada', $vuelta);

        $kilometraje = $request->filled('km_llegada') ? $request->integer('km_llegada') : null;
        $kmNuevo = $kilometraje !== $vuelta->km_llegada;

        $vuelta->update([
            'llegada_el' => $request->date('llegada_el'),
            'km_llegada' => $kilometraje,
            'llegada_por' => $request->user()?->id,
        ]);

        if ($kmNuevo) {
            $odometro->anotar($vuelta->tracto_id, $kilometraje, $request->user(), "Llegada a la base ({$vuelta->codigo})", OdometroDeUnidad::deLlegada($vuelta->id));
        }

        return back()->with('toast', ['type' => 'success', 'message' => "Llegada del ticket {$vuelta->codigo} registrada."]);
    }

    public function reabrir(Vuelta $vuelta): RedirectResponse
    {
        $this->authorize('reabrir', $vuelta);

        $vuelta->update([
            'estado' => $vuelta->viajes()->exists() ? EstadoVuelta::EnCurso : EstadoVuelta::Programada,
            'fin' => null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Ticket {$vuelta->codigo} reabierto."]);
    }

    public function destroy(Vuelta $vuelta): RedirectResponse
    {
        $this->authorize('delete', $vuelta);

        if (! $this->programador->puedeQuitarse($vuelta)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "La vuelta {$vuelta->codigo} ya tiene GR, combustible o viáticos: no se puede quitar.",
            ]);
        }

        $vuelta->delete();

        return back()->with('toast', ['type' => 'success', 'message' => "Vuelta {$vuelta->codigo} quitada."]);
    }

    /**
     * El aviso de la vuelta al conductor (o a su alterno), por la cola.
     */
    public function avisarConductor(Request $request, Vuelta $vuelta): RedirectResponse
    {
        $this->authorize('avisar', $vuelta);

        $datos = $request->validate([
            'numero' => ['required', 'string', 'max:20'],
            'destino' => ['nullable', 'string', 'max:30'],
        ]);

        $numero = $this->aviso->numeroWhatsapp($datos['numero']);

        if ($numero === null) {
            return back()->withErrors(['numero' => 'El número no es válido.']);
        }

        $this->avisos->encolarVueltaAlConductor($vuelta, $numero, $datos['destino'] ?? 'Conductor', $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Enviando la vuelta {$vuelta->codigo} a {$numero}…"]);
    }

    /**
     * Va siempre al número guardado del área: el destino de un aviso de área
     * no se elige al enviar.
     */
    public function avisarArea(Request $request, Vuelta $vuelta, AreaAviso $area): RedirectResponse
    {
        $this->authorize('avisar', $vuelta);

        $numero = $this->aviso->numeroWhatsapp($area->numero);

        if (! $area->activa || $numero === null) {
            return back()->with('toast', ['type' => 'error', 'message' => "{$area->nombre} no tiene un número activo."]);
        }

        $this->avisos->encolarVueltaAlArea($vuelta, $area, $numero, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Enviando la vuelta {$vuelta->codigo} a {$area->nombre}…"]);
    }

    /**
     * El kanban: cada vuelta abierta en su etapa —programada, bajando,
     * subiendo— y, en la última columna, las unidades que ya volvieron a la
     * base y esperan su siguiente vuelta. Las vueltas pasan solas de columna
     * a medida que llegan sus GR.
     *
     * @return list<array<string, mixed>>
     */
    private function tablero(Request $request): array
    {
        $hoy = RelojOperativo::fechaDeHoy();

        $ultimas = Vuelta::query()->selectRaw('max(id)')->groupBy('tracto_id');

        $vueltas = Vuelta::query()
            ->where(fn ($query) => $query
                ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
                ->orWhere(fn ($query) => $query
                    ->where('estado', EstadoVuelta::Cerrada)
                    ->whereIn('id', $ultimas)
                    ->whereDate('fin', '>=', $hoy->subDays(self::DIAS_EN_BASE_EN_TABLERO)->toDateString())))
            ->with($this->relaciones())->withExists(['abastecimientos', 'viaticos'])
            ->get();

        $gastos = $this->gastos->de($vueltas);

        // Una unidad que ya tiene programada la siguiente vuelta se ve ahí,
        // no también como disponible en la base.
        $conSiguiente = $vueltas
            ->filter(fn (Vuelta $vuelta): bool => $vuelta->estado === EstadoVuelta::Programada)
            ->pluck('tracto_id')
            ->all();

        return array_values($vueltas
            ->map(fn (Vuelta $vuelta): array => ['vuelta' => $vuelta, 'etapa' => $this->etapa($vuelta)])
            ->reject(fn (array $fila): bool => $fila['etapa'] === 'en_base'
                && in_array($fila['vuelta']->tracto_id, $conSiguiente, true))
            ->sortBy(fn (array $fila): string => $fila['vuelta']->inicio?->toDateString().'|'.$fila['vuelta']->tracto->placa)
            ->map(fn (array $fila): array => [
                ...$this->fila($fila['vuelta'], $fila['etapa'], $request),
                // Lo próximo que le toca desde donde va, y lo que quedó
                // atrás sin hacer: el seguimiento lo muestra en cada fila.
                ...$this->pasos->seguimiento($fila['vuelta'], $gastos[$fila['vuelta']->id]),
            ])
            ->all());
    }

    /**
     * Una fila de la tabla de programación: la vuelta con su etapa, dónde
     * va y los días que lleva.
     *
     * @return array<string, mixed>
     */
    private function fila(Vuelta $vuelta, string $etapa, Request $request): array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $llegada = $etapa === 'en_base' ? ($vuelta->llegada_el ?? $vuelta->fin) : null;
        $prevista = $etapa === 'en_vuelta' ? $vuelta->llegadaPrevista() : null;

        return [
            ...$this->vuelta($vuelta, $request),
            'etapa' => $etapa,
            'actual' => $this->dondeVa($vuelta),
            'dias_en_ruta' => in_array($etapa, ['en_ida', 'en_vuelta'], true) && $vuelta->inicio
                ? (int) $vuelta->inicio->diffInDays($hoy) + 1
                : null,
            'dias_en_base' => $llegada ? (int) $llegada->diffInDays($hoy) : null,
            'llegada' => $vuelta->llegoABase() ? [
                'fecha' => $vuelta->llegada_el->toDateString(),
                'km' => $vuelta->km_llegada,
                'por' => $vuelta->llegadaRegistradaPor?->name,
            ] : null,
            // Referencia para avisar de un atraso; la llegada la marca una
            // persona, nunca esta fecha.
            'llegada_prevista' => $prevista?->toDateString(),
            'atrasada' => $prevista !== null && $prevista->lt($hoy),
            'registrable_llegada' => $request->user()?->can('registrarLlegada', $vuelta) ?? false,
            'taller' => $vuelta->revisiones->first()?->resumen(),
            'taller_anteriores' => array_values($vuelta->revisiones
                ->skip(1)
                ->map(fn (RevisionTaller $revision): array => $revision->resumen())
                ->all()),
            'revisable' => $request->user()?->can('create', [RevisionTaller::class, $vuelta]) ?? false,
            'cerrable' => $request->user()?->can('cerrar', $vuelta) ?? false,
            'reabrible' => $request->user()?->can('reabrir', $vuelta) ?? false,
        ];
    }

    /**
     * Las GR del ticket en el orden en que se hizo el viaje, cada una con su
     * tramo.
     *
     * @return list<array<string, mixed>>
     */
    private function guias(Vuelta $vuelta, Request $request): array
    {
        $guias = [];
        $puedeQuitar = $vuelta->estaAbierta() && ($request->user()?->can('asignarGuias', Vuelta::class) ?? false);

        foreach ($vuelta->paradas as $parada) {
            foreach ($parada->viajes as $viaje) {
                $guias[] = [
                    'id' => $viaje->id,
                    'numero_gr' => $viaje->numero_gr,
                    'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                    'sentido' => $parada->sentido->value,
                    'tramo' => ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?'),
                    'tipo_carga_label' => $viaje->tipo_carga->label(),
                    'cliente' => $viaje->cliente,
                    'peso' => (float) $viaje->peso,
                    'unidad_peso' => $viaje->unidad_peso,
                    'no_programada' => $parada->fuera_de_programa,
                    'asignada_por' => $viaje->asignadaPor?->name,
                    'asignada_at' => $viaje->asignada_at?->format('Y-m-d H:i'),
                    'quitable' => $puedeQuitar,
                ];
            }
        }

        return $guias;
    }

    /**
     * Los avisos de WhatsApp del ticket ya armados y hasta dónde llegó cada
     * uno. Necesita `envios` cargados.
     *
     * @return array<string, mixed>
     */
    private function avisoDe(Vuelta $vuelta): array
    {
        $envios = ['conductor' => null, 'areas' => []];

        // Vienen del más nuevo al más viejo: el primero de cada destino gana.
        foreach ($vuelta->envios as $envio) {
            $datos = [
                'estado' => $envio->estado->value,
                'estado_label' => $envio->estado->label(),
                'destino' => $envio->destino,
                'hora' => ($envio->leido_at ?? $envio->entregado_at ?? $envio->enviado_at ?? $envio->created_at)->toIso8601String(),
                'error' => $envio->error,
            ];

            if ($envio->tipo === EnvioWhatsapp::TIPO_CONDUCTOR) {
                $envios['conductor'] ??= $datos;
            } elseif ($envio->area_aviso_id !== null) {
                $envios['areas'][$envio->area_aviso_id] ??= $datos;
            }
        }

        return [
            'mensaje' => $this->aviso->mensajeParaConductor($vuelta),
            'destinatarios' => $this->aviso->destinatarios($vuelta),
            'areas' => $this->aviso->avisosDeArea($vuelta),
            'envios' => $envios,
        ];
    }

    private function etapa(Vuelta $vuelta): string
    {
        return $vuelta->etapa();
    }

    /**
     * Dónde va la unidad: la última parada con GR o, si todavía no salió, la
     * primera del plan.
     *
     * @return array{texto: string, carga: string, fecha: string|null, hecha: bool}|null
     */
    private function dondeVa(Vuelta $vuelta): ?array
    {
        $hecha = $vuelta->paradas->last(fn (ParadaVuelta $parada): bool => $parada->viajes->isNotEmpty());
        $parada = $hecha ?? $vuelta->paradas->first();

        if ($parada === null) {
            return null;
        }

        return [
            'texto' => ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?'),
            'carga' => $parada->tipo_carga->label(),
            'fecha' => $parada->fecha?->toDateString(),
            'hecha' => $hecha !== null,
        ];
    }

    /**
     * Lo que necesita el formulario de programar. En closures: solo se
     * calculan cuando la página se pide entera, no en las recargas parciales.
     *
     * @return array<string, Closure>
     */
    private function opcionesDelFormulario(): array
    {
        return [
            'tramos' => fn (): array => $this->opcionesDeTramo(),
            'zonas' => fn (): array => ZonaRuta::options(),
            'cargas' => fn (): array => array_values(array_filter(
                TipoCarga::options(),
                fn (array $opcion): bool => $opcion['value'] !== TipoCarga::Sacos->value,
            )),
            'tractos' => fn (): array => $this->opcionesUnidades(),
            'carretas' => fn (): array => Vehiculo::query()
                ->where('tipo', TipoVehiculo::Carreta)
                ->where('estado', EstadoVehiculo::Activo)
                ->orderBy('placa')
                ->get(['id', 'placa'])
                ->map(fn (Vehiculo $carreta): array => ['id' => $carreta->id, 'placa' => $carreta->placa])
                ->all(),
            'conductores' => fn (): array => $this->opcionesConductores(),
            'clientes' => fn (): array => Cliente::query()
                ->where('activo', true)
                ->orderBy('alias')
                ->get(['id', 'alias', 'ruc'])
                ->map(fn (Cliente $cliente): array => ['id' => $cliente->id, 'alias' => $cliente->alias, 'ruc' => $cliente->ruc])
                ->all(),
        ];
    }

    /**
     * Las rutas activas del catálogo para cada tramo (principales y
     * complementos), con su dotación resumida y sus variantes, y los destinos
     * de la carga particular a otro destino.
     *
     * @return array{ida: list<array<string, mixed>>, regreso: list<array<string, mixed>>, destinos: list<array{lugar: string, zona: string}>}
     */
    private function opcionesDeTramo(): array
    {
        $opciones = ['ida' => [], 'regreso' => []];
        $rutas = Ruta::query()->where('activa', true)->enOrden()->with(['lineas.punto:id,nombre', 'variantes'])->get();

        foreach ($rutas as $ruta) {
            foreach (SentidoTramo::cases() as $sentido) {
                if (! $ruta->sirvePara($sentido)) {
                    continue;
                }

                $opciones[$sentido->value][] = [
                    'value' => (string) $ruta->id,
                    'label' => $ruta->nombre,
                    'descripcion' => $ruta->descripcion ?? '',
                    'es_complemento' => $ruta->es_complemento,
                    'sube_con' => $ruta->sube_con?->value,
                    'pide_destino' => $ruta->pide_destino,
                    'paradas' => $ruta->paradas,
                    'tope' => $ruta->topePara(null),
                    'dotacion' => $this->resumenDeDotacion($ruta->lineasPara(null)),
                    'variantes' => $ruta->variantes->map(fn (VarianteRuta $variante): array => [
                        'id' => $variante->id,
                        'nombre' => $variante->nombre,
                        'tope' => $ruta->topePara($variante),
                    ])->values()->all(),
                ];
            }
        }

        return [
            ...$opciones,
            'destinos' => array_values(DestinoParticular::query()
                ->where('activo', true)
                ->enOrden()
                ->get()
                ->map(fn (DestinoParticular $destino): array => ['lugar' => $destino->lugar, 'zona' => $destino->zona->value])
                ->all()),
        ];
    }

    /**
     * La dotación en una línea: «Mina 100 gal · Arequipa 120 gal · viáticos
     * S/ 843».
     *
     * @param  list<array{tipo: string, punto: string|null, concepto: string, galones: float|null, monto: float|null}>  $lineas
     */
    private function resumenDeDotacion(array $lineas): string
    {
        $combustible = array_map(
            fn (array $linea): string => ($linea['punto'] ?? $linea['concepto']).' '.($linea['galones'] === null ? '¿?' : rtrim(rtrim(number_format($linea['galones'], 1, '.', ''), '0'), '.')).' gal',
            array_values(array_filter($lineas, fn (array $linea): bool => $linea['tipo'] === TipoLineaAbastecimiento::Combustible->value)),
        );
        $viaticos = array_sum(array_column(array_filter($lineas, fn (array $linea): bool => $linea['tipo'] !== TipoLineaAbastecimiento::Combustible->value), 'monto'));

        return implode(' · ', array_filter([...$combustible, $viaticos > 0 ? 'viáticos S/ '.number_format($viaticos, 0) : null]));
    }

    /**
     * Los tractos activos con la carreta y el conductor de su última GR:
     * elegir la unidad deja puestos los dos, que casi nunca cambian de una
     * vuelta a la siguiente. También dónde dejó la unidad esa GR, para que la
     * plantilla arranque desde ahí.
     *
     * @return array<int, array<string, mixed>>
     */
    private function opcionesUnidades(): array
    {
        // La última por fecha de traslado, no por orden de carga: una GR
        // importada tarde no le gana a la más reciente.
        $ultimas = Viaje::query()
            ->whereNotNull('tracto_id')
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('id')
            ->get(['id', 'tracto_id', 'carreta_id', 'conductor_id', 'destino', 'numero_gr', 'fecha_traslado', 'placa_carreta', 'conductor_nombre', 'tipo_carga', 'parada_vuelta_id'])
            ->unique('tracto_id')
            ->keyBy('tracto_id');

        return Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->orderBy('placa')
            ->get(['id', 'placa'])
            ->map(function (Vehiculo $tracto) use ($ultimas): array {
                /** @var Viaje|null $ultima */
                $ultima = $ultimas->get($tracto->id);

                return [
                    'id' => $tracto->id,
                    'placa' => $tracto->placa,
                    'carreta_id' => $ultima?->carreta_id,
                    'conductor_id' => $ultima?->conductor_id,
                    'zona_actual' => $ultima ? $this->dondeQuedo($ultima->destino)?->value : null,
                    'trae' => $ultima ? $this->cargaQueTrae($ultima) : null,
                    'llego_a' => $ultima ? app(AsignadorVueltas::class)->lugar($ultima->destino) : null,
                    // Lo que dice el papel, aunque no calce con el padrón:
                    // así se ve de dónde salió lo que se dejó puesto.
                    'ultima_gr' => $ultima ? [
                        'numero' => $ultima->numero_gr,
                        'fecha' => $ultima->fecha_traslado->toDateString(),
                        'placa_carreta' => $ultima->placa_carreta,
                        'conductor' => $ultima->conductor_nombre,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * La carga con la que la unidad sube de Juliaca a la mina en el ticket
     * siguiente: la de su última GR, si iba a la mina cargada (escoria,
     * materiales, sacos). El combustible de esa GR alcanzó hasta Juliaca; la
     * subida va en la ida que se programa ahora, sin GR nueva.
     *
     * @return array{viaje_id: int, numero_gr: string|null, tipo_carga: string, tipo_carga_label: string, ticket: string|null}|null
     */
    private function cargaQueTrae(Viaje $ultima): ?array
    {
        if (ZonaRuta::desdeDireccion($ultima->destino) !== ZonaRuta::Mina || in_array($ultima->tipo_carga, [TipoCarga::Vacio, TipoCarga::Concentrado], true)) {
            return null;
        }

        return [
            'viaje_id' => $ultima->id,
            'numero_gr' => $ultima->numero_gr,
            'tipo_carga' => $ultima->tipo_carga->value,
            'tipo_carga_label' => $ultima->tipo_carga->label(),
            'ticket' => $ultima->parada_vuelta_id ? Vuelta::query()->whereHas('paradas', fn ($query) => $query->whereKey($ultima->parada_vuelta_id))->value('codigo') : null,
        ];
    }

    /**
     * Dónde quedó la unidad según el destino de su última GR. El Sur y Cusco
     * son de paso: de ahí sigue a Juliaca, así que la unidad cuenta como en
     * Juliaca y el ticket siguiente arranca ahí.
     */
    private function dondeQuedo(string $destino): ?ZonaRuta
    {
        $zona = ZonaRuta::desdeDireccion($destino);

        return $zona !== null && $zona->esSierra() && ! $zona->esBase() ? ZonaRuta::Juliaca : $zona;
    }

    /**
     * Los conductores activos con la unidad y la carreta de su última GR:
     * elegir primero al conductor deja puestas las dos, como en la
     * programación de siempre.
     *
     * @return array<int, array<string, mixed>>
     */
    private function opcionesConductores(): array
    {
        $ultimas = Viaje::query()
            ->whereNotNull('conductor_id')
            ->whereNotNull('tracto_id')
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('id')
            ->get(['conductor_id', 'tracto_id', 'carreta_id'])
            ->unique('conductor_id')
            ->keyBy('conductor_id');

        return Conductor::query()
            ->where('activo', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos'])
            ->map(fn (Conductor $conductor): array => [
                'id' => $conductor->id,
                'nombre' => "{$conductor->apellidos} {$conductor->nombres}",
                'tracto_id' => $ultimas->get($conductor->id)?->tracto_id,
                'carreta_id' => $ultimas->get($conductor->id)?->carreta_id,
            ])
            ->all();
    }

    /**
     * Las relaciones de la vuelta que pinta la pantalla: las simples por
     * nombre, las GR de cada parada con sus columnas y en orden.
     *
     * @return array<int|string, string|Closure>
     */
    private function relaciones(): array
    {
        return [
            'tracto:id,placa',
            'carreta:id,placa',
            'conductor:id,nombres,apellidos,telefono,telefono_alterno',
            'revisiones.usuario:id,name',
            'rutas',
            'llegadaRegistradaPor:id,name',
            'paradas.viajeContinuado:id,numero_gr,parada_vuelta_id',
            'paradas.viajeContinuado.parada.vuelta:id,codigo',
            'paradas.viajes' => fn ($query) => $query
                ->select(['id', 'parada_vuelta_id', 'numero_gr', 'fecha_traslado', 'peso', 'unidad_peso', 'cliente', 'origen', 'destino', 'tipo_carga', 'asignada_por', 'asignada_at'])
                ->with('asignadaPor:id,name')
                ->orderBy('fecha_traslado')
                ->orderBy('id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vuelta(Vuelta $vuelta, Request $request): array
    {
        $conductor = $vuelta->conductor;
        $programada = $vuelta->origen === OrigenVuelta::Programacion;

        // Una parada sin lugar propio arranca donde terminó la anterior si es
        // la misma zona: de Paracas (lo que dice la GR) sigue a Lima, no de
        // «Pisco», y el recorrido no se corta.
        $origenes = [];
        $anterior = null;

        foreach ($vuelta->paradas as $parada) {
            if ($parada->lugar_origen === null && $anterior?->lugar_destino !== null
                && $anterior->zona_destino === $parada->zona_origen) {
                $origenes[$parada->id] = $anterior->lugar_destino;
            }

            $anterior = $parada;
        }

        return [
            'id' => $vuelta->id,
            'codigo' => $vuelta->codigo,
            'tipo' => $vuelta->tipo->value,
            'tipo_label' => $vuelta->tipo->label(),
            'estado' => $vuelta->estado->value,
            'estado_label' => $vuelta->estado->label(),
            'origen' => $vuelta->origen->value,
            'inicio' => $vuelta->inicio?->toDateString(),
            'fin' => $vuelta->fin?->toDateString(),
            'dias' => $vuelta->inicio && $vuelta->fin ? (int) $vuelta->inicio->diffInDays($vuelta->fin) + 1 : null,
            'tracto_id' => $vuelta->tracto_id,
            'placa' => $vuelta->tracto->placa,
            'carreta_id' => $vuelta->carreta_id,
            'conductor_id' => $vuelta->conductor_id,
            'carreta' => $vuelta->carreta?->placa,
            'conductor' => $conductor ? "{$conductor->nombres} {$conductor->apellidos}" : null,
            'editable' => $request->user()?->can('update', $vuelta) ?? false,
            'quitable' => ($request->user()?->can('delete', $vuelta) ?? false)
                && $vuelta->paradas->every(fn (ParadaVuelta $parada): bool => $parada->viajes->isEmpty())
                && ! $vuelta->abastecimientos_exists
                && ! $vuelta->viaticos_exists,
            'ida' => $vuelta->paradasDe(SentidoTramo::Ida)->map(fn (ParadaVuelta $parada): array => $this->parada($parada, $programada, $origenes[$parada->id] ?? null))->all(),
            'regreso' => $vuelta->paradasDe(SentidoTramo::Regreso)->map(fn (ParadaVuelta $parada): array => $this->parada($parada, $programada, $origenes[$parada->id] ?? null))->all(),
            // Las rutas elegidas: el formulario las vuelve a marcar al editar.
            'rutas' => $vuelta->rutas->map(fn (RutaVuelta $ruta): array => [
                'id' => $ruta->id,
                'sentido' => $ruta->sentido->value,
                'ruta_id' => $ruta->ruta_id,
                'variante_ruta_id' => $ruta->variante_ruta_id,
                'nombre' => $ruta->nombre,
                'variante' => $ruta->variante,
                'es_complemento' => $ruta->es_complemento,
                'variante_pendiente' => $ruta->variante_pendiente,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parada(ParadaVuelta $parada, bool $deVueltaProgramada, ?string $origenHeredado = null): array
    {
        return [
            'id' => $parada->id,
            'sentido' => $parada->sentido->value,
            'origen' => $parada->lugar_origen ?? $origenHeredado ?? $parada->zona_origen?->label(),
            'destino' => $parada->lugar_destino ?? $parada->zona_destino?->label(),
            'zona_origen' => $parada->zona_origen?->value,
            'zona_destino' => $parada->zona_destino?->value,
            'lugar_origen' => $parada->lugar_origen,
            'lugar_destino' => $parada->lugar_destino,
            'tipo_carga' => $parada->tipo_carga->value,
            'tipo_carga_label' => $parada->tipo_carga->label(),
            // Lo que suele tardar: el Gantt lo dibuja así de largo.
            'dias_viaje' => ZonaRuta::diasDeViaje($parada->zona_origen, $parada->zona_destino),
            'cliente_id' => $parada->cliente_id,
            'cliente' => $parada->cliente,
            'fecha' => $parada->fecha?->toDateString(),
            // Lo planeado que todavía no tiene GR, y lo que llegó sin estar
            // en el plan: las dos diferencias entre lo programado y lo real.
            // La carga que sigue de una GR del ticket anterior no espera otra.
            'pendiente' => $deVueltaProgramada && ! $parada->esVacio() && ! $parada->esContinuacion() && $parada->viajes->isEmpty(),
            'fuera_de_programa' => $parada->fuera_de_programa,
            'viaje_continuado_id' => $parada->viaje_continuado_id,
            'continua' => $parada->viajeContinuado ? [
                'numero_gr' => $parada->viajeContinuado->numero_gr,
                'ticket' => $parada->viajeContinuado->parada?->vuelta?->codigo,
            ] : null,
            'guias' => $parada->viajes
                ->map(fn (Viaje $viaje): array => [
                    'id' => $viaje->id,
                    'numero_gr' => $viaje->numero_gr,
                    'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                ])
                ->all(),
        ];
    }
}
