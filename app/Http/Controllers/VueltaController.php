<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarVueltaRequest;
use App\Models\AreaAviso;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\DestinoParticular;
use App\Models\OpcionTramo;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AsignadorVueltas;
use App\Services\AvisoDeVuelta;
use App\Services\AvisosPorWhatsapp;
use App\Services\ProgramadorVueltas;
use App\Services\RelojOperativo;
use Carbon\CarbonImmutable;
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
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        return Inertia::render('vueltas/index', [
            // Hoy: la fecha de salida que se propone al programar.
            'fecha' => RelojOperativo::fechaDeHoy()->toDateString(),
            'tablero' => $this->tablero($request),
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

    public function store(GuardarVueltaRequest $request): RedirectResponse
    {
        $this->authorize('create', Vuelta::class);

        $vuelta = $this->programador->programar($request->datos());

        return back()->with('toast', ['type' => 'success', 'message' => "Vuelta {$vuelta->codigo} programada."]);
    }

    public function update(GuardarVueltaRequest $request, Vuelta $vuelta): RedirectResponse
    {
        $this->authorize('update', $vuelta);

        $this->programador->actualizar($vuelta, $request->datos());

        return back()->with('toast', ['type' => 'success', 'message' => "Vuelta {$vuelta->codigo} actualizada."]);
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
            ->map(fn (array $fila): array => $this->fila($fila['vuelta'], $fila['etapa'], $request))
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
        $llegada = $etapa === 'en_base' ? $this->ultimaGuia($vuelta) : null;

        return [
            ...$this->vuelta($vuelta, $request),
            'etapa' => $etapa,
            'actual' => $this->dondeVa($vuelta),
            'dias_en_ruta' => in_array($etapa, ['en_ida', 'en_vuelta'], true) && $vuelta->inicio
                ? (int) $vuelta->inicio->diffInDays($hoy) + 1
                : null,
            'dias_en_base' => $llegada ? (int) $llegada->diffInDays($hoy) : null,
            'taller' => $vuelta->revisiones->first()?->resumen(),
            'taller_anteriores' => array_values($vuelta->revisiones
                ->skip(1)
                ->map(fn (RevisionTaller $revision): array => $revision->resumen())
                ->all()),
            'revisable' => $request->user()?->can('create', [RevisionTaller::class, $vuelta]) ?? false,
        ];
    }

    private function etapa(Vuelta $vuelta): string
    {
        return $vuelta->etapa();
    }

    private function ultimaGuia(Vuelta $vuelta): ?CarbonImmutable
    {
        $fecha = $vuelta->paradas
            ->flatMap(fn (ParadaVuelta $parada) => $parada->viajes)
            ->max(fn (Viaje $viaje): string => $viaje->fecha_traslado->toDateString());

        return $fecha === null ? null : CarbonImmutable::parse($fecha);
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
     * Las opciones de ida y de vuelta y los destinos de la carga particular,
     * tal como están en la configuración de la programación.
     *
     * @return array{ida: list<array<string, mixed>>, regreso: list<array<string, mixed>>, destinos: list<array{lugar: string, zona: string}>}
     */
    private function opcionesDeTramo(): array
    {
        $opciones = ['ida' => [], 'regreso' => []];

        foreach (OpcionTramo::query()->where('activa', true)->enOrden()->get() as $opcion) {
            $opciones[$opcion->sentido->value][] = [
                'value' => (string) $opcion->id,
                'label' => $opcion->nombre,
                'descripcion' => $opcion->descripcion ?? '',
                'pide_destino' => $opcion->pide_destino,
                'paradas' => $opcion->paradas,
            ];
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
            ->get(['id', 'tracto_id', 'carreta_id', 'conductor_id', 'destino', 'numero_gr', 'fecha_traslado', 'placa_carreta', 'conductor_nombre'])
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
            'paradas.viajes' => fn ($query) => $query
                ->select(['id', 'parada_vuelta_id', 'numero_gr', 'fecha_traslado', 'peso', 'unidad_peso'])
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
            'cliente_id' => $parada->cliente_id,
            'cliente' => $parada->cliente,
            'fecha' => $parada->fecha?->toDateString(),
            // Lo planeado que todavía no tiene GR, y lo que llegó sin estar
            // en el plan: las dos diferencias entre lo programado y lo real.
            'pendiente' => $deVueltaProgramada && ! $parada->esVacio() && $parada->viajes->isEmpty(),
            'fuera_de_programa' => $parada->fuera_de_programa,
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
