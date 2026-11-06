<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\CorregirAbastecimientoRequest;
use App\Http\Requests\RegistrarAbastecimientoRequest;
use App\Http\Requests\RegistrarDespachoRequest;
use App\Models\Abastecimiento;
use App\Models\ParadaVuelta;
use App\Models\PuntoAbastecimiento;
use App\Models\RutaVuelta;
use App\Models\VarianteRuta;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use App\Services\DotacionVuelta;
use App\Services\GastosDelTicket;
use App\Services\OdometroDeUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero de abastecimiento: cada ticket abierto con las rutas de cada
 * tramo (lo autorizado), lo que ya se le autorizó, lo que le falta y lo que de verdad
 * se despachó; y el registro de lo que se autoriza por el QR de Repsol (o en
 * Kio).
 */
class AbastecimientoController extends Controller
{
    /** Cuántos registros recientes se muestran debajo del tablero. */
    private const RECIENTES = 40;

    public function __construct(private readonly GastosDelTicket $gastos) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Abastecimiento::class);

        // Todo ticket abierto, también el que ya llegó a la base: sigue acá
        // hasta que se liquida y se cierra (el despacho del regreso a veces
        // se registra tarde).
        $vueltas = Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->with([
                'tracto:id,placa',
                'conductor:id,nombres,apellidos',
                'paradas.viajes:id,parada_vuelta_id,numero_gr,origen,destino,peso,unidad_peso,fecha_traslado',
                'revision.usuario:id,name',
                'rutas.ruta.variantes',
            ])
            ->get()
            ->sortBy(fn (Vuelta $vuelta): string => $vuelta->tracto->placa.'|'.$vuelta->inicio?->toDateString())
            ->values();

        $gastos = $this->gastos->de($vueltas);

        return Inertia::render('abastecimiento/index', [
            'vueltas' => $vueltas
                ->map(fn (Vuelta $vuelta): array => [
                    'id' => $vuelta->id,
                    'codigo' => $vuelta->codigo,
                    'estado' => $vuelta->estado->value,
                    'estado_label' => $vuelta->estado->label(),
                    'inicio' => $vuelta->inicio?->toDateString(),
                    'tracto' => ['id' => $vuelta->tracto->id, 'placa' => $vuelta->tracto->placa],
                    'conductor' => $vuelta->conductor ? "{$vuelta->conductor->nombres} {$vuelta->conductor->apellidos}" : null,
                    'etapa' => $vuelta->etapa(),
                    'taller' => $vuelta->revision?->resumen(),
                    'bloqueo_gastos' => $vuelta->bloqueoDeGastos(),
                    'recorrido' => $this->recorrido($vuelta),
                    ...$gastos[$vuelta->id],
                ])
                ->all(),
            'recientes' => Abastecimiento::query()
                ->with(['vehiculo:id,placa', 'conductor:id,nombres,apellidos', 'punto:id,nombre', 'vuelta:id,codigo', 'registradoPor:id,name'])
                ->latest('fecha')
                ->latest('id')
                ->limit(self::RECIENTES)
                ->get()
                ->map(fn (Abastecimiento $abastecimiento): array => [
                    'id' => $abastecimiento->id,
                    'fecha' => $abastecimiento->fecha->toDateString(),
                    'placa' => $abastecimiento->vehiculo->placa,
                    'conductor' => $abastecimiento->conductor?->nombre_completo,
                    'punto' => $abastecimiento->nombrePunto(),
                    'fuera_de_lista' => $abastecimiento->punto_abastecimiento_id === null && filled($abastecimiento->punto_libre),
                    'vuelta' => $abastecimiento->vuelta?->codigo,
                    'sentido' => $abastecimiento->sentido?->value,
                    'galones' => (float) $abastecimiento->galones,
                    'galones_despachados' => $abastecimiento->galones_despachados === null ? null : (float) $abastecimiento->galones_despachados,
                    'precio_galon' => $abastecimiento->precio_galon === null ? null : (float) $abastecimiento->precio_galon,
                    'importe' => $abastecimiento->importe === null ? null : (float) $abastecimiento->importe,
                    'anulado' => $abastecimiento->estaAnulado(),
                    'observacion' => $abastecimiento->observacion,
                    'registrado_por' => $abastecimiento->registradoPor?->name,
                ])
                ->all(),
            'tractos' => fn (): array => $this->opcionesTractos(),
            'puntos' => fn (): array => $this->opcionesPuntos(),
            'puedeExcederTope' => fn (): bool => request()->user()?->can('excederTope', Abastecimiento::class) ?? false,
        ]);
    }

    public function store(RegistrarAbastecimientoRequest $request): RedirectResponse
    {
        $this->authorize('create', Abastecimiento::class);

        $vuelta = $request->vuelta();

        DB::transaction(function () use ($request, $vuelta): void {
            // Con el ticket bloqueado se vuelve a mirar el tope: dos registros
            // a la vez no lo pasan entre los dos.
            Vuelta::query()->whereKey($vuelta->id)->lockForUpdate()->first();

            if (($error = $request->errorDeTope($vuelta)) !== null) {
                throw ValidationException::withMessages([$error[0] => $error[1]]);
            }

            $this->registrar($request, $vuelta);
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Combustible autorizado en el ticket {$vuelta->codigo}.",
        ]);
    }

    private function registrar(RegistrarAbastecimientoRequest $request, Vuelta $vuelta): void
    {
        Abastecimiento::query()->create([
            ...$request->datosDelCombustible(),
            'vehiculo_id' => $vuelta->tracto_id,
            'vuelta_id' => $vuelta->id,
            'conductor_id' => $vuelta->conductor_id,
            'user_id' => $request->user()?->id,
        ]);
    }

    /**
     * Corregir lo autorizado cuando se registró mal (otro punto, otros
     * galones, otro tramo). El ticket y la unidad no cambian: si era de otro
     * ticket, se anula y se registra en el correcto.
     */
    public function update(CorregirAbastecimientoRequest $request, Abastecimiento $abastecimiento): RedirectResponse
    {
        $this->authorize('update', $abastecimiento);

        $vuelta = $request->vuelta();

        DB::transaction(function () use ($request, $vuelta, $abastecimiento): void {
            Vuelta::query()->whereKey($vuelta->id)->lockForUpdate()->first();

            if (($error = $request->errorDeTope($vuelta)) !== null) {
                throw ValidationException::withMessages([$error[0] => $error[1]]);
            }

            $abastecimiento->update($request->datosDelCombustible());
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Combustible corregido.']);
    }

    /**
     * Solo el precio del voucher: el importe se recalcula con los galones
     * que ya tiene.
     */
    public function precio(Request $request, Abastecimiento $abastecimiento): RedirectResponse
    {
        $this->authorize('corregirPrecio', $abastecimiento);

        $precio = (float) $request->validate([
            'precio_galon' => ['required', 'numeric', 'min:0.01', 'max:99'],
        ])['precio_galon'];

        $abastecimiento->update([
            'precio_galon' => $precio,
            'importe' => round((float) $abastecimiento->galones_despachados * $precio, 2),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Precio corregido.']);
    }

    /**
     * Nada se borra: queda anulado, con quién, cuándo y por qué.
     */
    public function anular(Request $request, Abastecimiento $abastecimiento): RedirectResponse
    {
        $this->authorize('anular', $abastecimiento);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

        $abastecimiento->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $datos['motivo'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Combustible anulado.']);
    }

    /**
     * Lo que de verdad cargó, según el voucher. El kilometraje también queda
     * como lectura para mantenimiento.
     */
    public function despacho(RegistrarDespachoRequest $request, Abastecimiento $abastecimiento, OdometroDeUnidad $odometro): RedirectResponse
    {
        $this->authorize('registrarDespacho', $abastecimiento);

        $galones = $request->float('galones_despachados');
        $precio = $request->float('precio_galon');
        $kilometraje = $request->filled('kilometraje') ? $request->integer('kilometraje') : null;
        $kmNuevo = $kilometraje !== $abastecimiento->kilometraje;

        $abastecimiento->update([
            'galones_despachados' => $galones,
            'precio_galon' => $precio,
            'importe' => round($galones * $precio, 2),
            'kilometraje' => $kilometraje,
            'despachado_el' => $request->date('despachado_el'),
            'despacho_por' => $request->user()?->id,
        ]);

        if ($request->hasFile('foto_voucher')) {
            $abastecimiento->addMediaFromRequest('foto_voucher')->toMediaCollection('voucher');
        }

        if ($kmNuevo) {
            $odometro->anotar(
                $abastecimiento->vehiculo_id,
                $kilometraje,
                $request->user(),
                'Al cargar combustible ('.($abastecimiento->vuelta->codigo ?? 'sin ticket').')',
                OdometroDeUnidad::deDespacho($abastecimiento->id),
            );
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Despacho registrado.']);
    }

    /**
     * Elige la variante (por peso) de una ruta del ticket: hasta entonces el
     * tramo no tiene tope. El sistema sugiere la que corresponde al peso de
     * las GR; la elige una persona.
     */
    public function elegirVariante(Request $request, RutaVuelta $rutaVuelta, DotacionVuelta $dotacion): RedirectResponse
    {
        $this->authorize('elegirVariante', $rutaVuelta);

        $request->validate([
            'variante_ruta_id' => ['required', 'integer', Rule::exists('variantes_ruta', 'id')->where('ruta_id', $rutaVuelta->ruta_id)],
        ]);

        $variante = VarianteRuta::query()->findOrFail($request->integer('variante_ruta_id'));

        // La variante que faltaba se completa siempre; cambiar una ya elegida
        // movería el tope de lo que ya se autorizó contra ella.
        if ($rutaVuelta->variante_ruta_id !== null
            && $rutaVuelta->variante_ruta_id !== $variante->id
            && $rutaVuelta->vuelta->tramoConGastos($rutaVuelta->sentido)) {
            throw ValidationException::withMessages([
                'variante_ruta_id' => "«{$rutaVuelta->nombre}» ya tiene combustible o viáticos autorizados con la variante {$rutaVuelta->variante}: anula esos gastos antes de cambiarla.",
            ]);
        }

        $dotacion->elegirVariante($rutaVuelta, $variante, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "«{$rutaVuelta->nombre}»: {$variante->nombre}."]);
    }

    /**
     * Las paradas de la vuelta en una línea, para reconocerla de un vistazo.
     *
     * @return list<array{sentido: string, texto: string, carga: string}>
     */
    private function recorrido(Vuelta $vuelta): array
    {
        return array_values($vuelta->paradas
            ->map(fn (ParadaVuelta $parada): array => [
                'sentido' => $parada->sentido === SentidoTramo::Ida ? 'ida' : 'regreso',
                'texto' => ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?'),
                'carga' => $parada->tipo_carga->label(),
            ])
            ->all());
    }

    /**
     * Los puntos activos con el último precio por galón que se pagó en cada
     * uno: elegir el punto deja puesto ese precio (si cambió, se corrige).
     *
     * @return list<array{id: int, nombre: string, ultimo_precio: float|null}>
     */
    private function opcionesPuntos(): array
    {
        $puntos = PuntoAbastecimiento::query()
            ->where('activo', true)
            ->enOrden()
            ->get(['id', 'nombre']);

        // El último precio conocido en cada punto: el del voucher si ya
        // llegó; si no, con el que se autorizó.
        $precios = Abastecimiento::query()
            ->whereIn('punto_abastecimiento_id', $puntos->pluck('id'))
            ->where(fn ($query) => $query->whereNotNull('precio_galon')->orWhereNotNull('precio_autorizado'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['punto_abastecimiento_id', 'precio_galon', 'precio_autorizado'])
            ->unique('punto_abastecimiento_id')
            ->keyBy('punto_abastecimiento_id');

        return array_values($puntos
            ->map(fn (PuntoAbastecimiento $punto): array => [
                'id' => $punto->id,
                'nombre' => $punto->nombre,
                'ultimo_precio' => ($precio = $this->ultimoPrecio($precios->get($punto->id))) === null ? null : (float) $precio,
            ])
            ->all());
    }

    /**
     * El precio del voucher si llegó; si no, con el que se autorizó.
     */
    private function ultimoPrecio(?Abastecimiento $registro): ?string
    {
        return $registro === null ? null : ($registro->precio_galon ?? $registro->precio_autorizado);
    }

    /**
     * Los tractos activos con sus tickets abiertos: quien registra elige el
     * ticket; si la unidad tiene uno solo, queda puesto.
     *
     * @return list<array{id: int, placa: string, tickets: list<array{id: int, codigo: string|null, etapa: string, bloqueo: string|null, tramos: list<string>}>}>
     */
    private function opcionesTractos(): array
    {
        $abiertas = Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->orderBy('inicio')
            ->with(['revision', 'rutas', 'paradas.viajes:id,parada_vuelta_id'])
            ->get()
            ->groupBy('tracto_id');

        return array_values(Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->orderBy('placa')
            ->get(['id', 'placa'])
            ->map(fn (Vehiculo $tracto): array => [
                'id' => $tracto->id,
                'placa' => $tracto->placa,
                'tickets' => array_values($abiertas->get($tracto->id, collect())
                    ->map(fn (Vuelta $vuelta): array => [
                        'id' => $vuelta->id,
                        'codigo' => $vuelta->codigo,
                        'etapa' => $vuelta->etapa(),
                        'bloqueo' => $vuelta->bloqueoDeGastos(),
                        // Los tramos con tope (ruta y variante elegidas):
                        // solo a esos se les puede autorizar combustible.
                        'tramos' => array_values(array_map(
                            fn (SentidoTramo $sentido): string => $sentido->value,
                            array_filter(SentidoTramo::cases(), fn (SentidoTramo $sentido): bool => $vuelta->topeDe($sentido) !== null),
                        )),
                    ])
                    ->all()),
            ])
            ->all());
    }
}
