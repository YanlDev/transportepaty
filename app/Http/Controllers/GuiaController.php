<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGre;
use App\Enums\MotivoTraslado;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Http\Requests\EmitirGuiaRequest;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\PuntoTraslado;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\ConstructorGuiaTransportista;
use App\Services\EnviadorGuiaSunat;
use App\Services\FirmadorGuiaTransportista;
use App\Services\SecuenciaGuia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Emisión de la Guía de Remisión Electrónica del Transportista.
 *
 * Invierte el flujo que había: en vez de tipear la guía en el portal SOL,
 * bajar el PDF e importarlo, acá se llena una vez y el viaje nace de la
 * emisión. Todo lo que el sistema ya sabe —TUC de la placa, licencia del
 * conductor, RUC del cliente— se completa solo.
 */
class GuiaController extends Controller
{
    public function __construct(
        private readonly ConstructorGuiaTransportista $constructor,
        private readonly FirmadorGuiaTransportista $firmador,
        private readonly EnviadorGuiaSunat $enviador,
        private readonly SecuenciaGuia $secuencia,
    ) {}

    /**
     * Las guías emitidas desde el sistema. No lista los viajes importados de
     * PDF: esos los emitió el portal SOL y no tienen estado que seguir acá.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Viaje::class);

        return Inertia::render('guias/index', [
            'guias' => Viaje::query()
                ->where('gre_estado', '!=', EstadoGre::Pendiente->value)
                ->with(['puntoPartida:id,nombre', 'puntoLlegada:id,nombre'])
                ->orderByDesc('id')
                ->paginate(25)
                ->through(fn (Viaje $viaje): array => [
                    'id' => $viaje->id,
                    'numero_gr' => $viaje->numero_gr,
                    'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                    'cliente' => $viaje->cliente,
                    'destinatario' => $viaje->destinatario,
                    'partida' => $viaje->puntoPartida->nombre ?? $viaje->origen,
                    'llegada' => $viaje->puntoLlegada->nombre ?? $viaje->destino,
                    'placa_tracto' => $viaje->placa_tracto,
                    'estado' => $viaje->gre_estado->value,
                    'estado_label' => $viaje->gre_estado->label(),
                    'ticket' => $viaje->gre_ticket,
                    'mensaje' => $viaje->gre_respuesta['mensaje'] ?? null,
                    'reintentable' => ! $viaje->gre_estado->esFinal(),
                ]),
            'credencialesConfiguradas' => $this->credencialesConfiguradas(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Viaje::class);

        return Inertia::render('guias/create', [
            'serie' => (string) config('gre.serie'),
            'emisorConfigurado' => $this->emisorConfigurado(),
            'clientes' => Cliente::query()
                ->where('activo', true)
                ->orderBy('alias')
                ->get(['id', 'ruc', 'razon_social', 'alias'])
                ->map(fn (Cliente $cliente): array => [
                    'value' => (string) $cliente->id,
                    'label' => $cliente->alias,
                    'ruc' => $cliente->ruc,
                    'razon_social' => $cliente->razon_social,
                ])
                ->all(),
            'tractos' => $this->vehiculos(TipoVehiculo::Tracto),
            'carretas' => $this->vehiculos(TipoVehiculo::Carreta),
            'conductores' => Conductor::query()
                ->where('activo', true)
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'documento', 'licencia'])
                ->map(fn (Conductor $conductor): array => [
                    'value' => (string) $conductor->id,
                    'label' => "{$conductor->apellidos} {$conductor->nombres}",
                    'documento' => $conductor->documento,
                    // Sin licencia la guía no se puede emitir: se avisa en el
                    // formulario y no al final, cuando SUNAT la rechaza.
                    'licencia' => $conductor->licencia,
                ])
                ->all(),
            // Lugares ya usados: la mayoría de los viajes repiten ruta, así que
            // elegir uno de acá evita volver a escribir dirección y ubigeo.
            'puntosFrecuentes' => PuntoTraslado::query()
                ->activos()
                ->withCount(['viajesComoPartida', 'viajesComoLlegada'])
                ->get()
                ->sortByDesc(fn (PuntoTraslado $punto): int => $punto->viajes_como_partida_count + $punto->viajes_como_llegada_count)
                ->take(20)
                ->map(fn (PuntoTraslado $punto): array => [
                    'value' => (string) $punto->id,
                    'label' => $punto->nombre,
                    'ubigeo' => $punto->ubigeo,
                    'direccion' => $punto->direccion,
                ])
                ->values()
                ->all(),
            'tiposCarga' => TipoCarga::opcionesDeViaje(),
            'motivosTraslado' => MotivoTraslado::options(),
        ]);
    }

    /**
     * Arma la guía, la firma y —si hay credenciales— la manda a SUNAT.
     *
     * El viaje se crea igual aunque el envío falle: el documento ya existe y
     * tiene número reservado, así que perderlo obligaría a saltear un
     * correlativo. Queda en «generada» y se reintenta el envío.
     */
    public function store(EmitirGuiaRequest $request): RedirectResponse
    {
        $this->authorize('create', Viaje::class);

        $viaje = DB::transaction(function () use ($request): Viaje {
            $correlativo = $this->secuencia->siguiente((string) config('gre.serie'));

            return Viaje::create([
                ...$this->datosDelViaje($request),
                'numero_gr' => $this->secuencia->formatear((string) config('gre.serie'), $correlativo),
                'gre_estado' => EstadoGre::Generada,
            ]);
        });

        return $this->emitir($viaje);
    }

    /**
     * Reintenta la emisión de una guía que quedó generada o rechazada.
     */
    public function reintentar(Viaje $viaje): RedirectResponse
    {
        $this->authorize('update', $viaje);

        if ($viaje->gre_estado->esFinal()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "La guía {$viaje->numero_gr} ya está {$viaje->gre_estado->label()}.",
            ]);
        }

        return $this->emitir($viaje);
    }

    private function emitir(Viaje $viaje): RedirectResponse
    {
        try {
            $correlativo = (int) Str::afterLast($viaje->numero_gr, '-');
            $guia = $this->constructor->construir($viaje, (string) $correlativo);
            $nombre = $this->firmador->nombreArchivo($guia);
            $xml = $this->firmador->firmar($guia);

            // El XML firmado se guarda antes de enviar: es el documento, y si
            // el envío falla hay que poder reintentar con el mismo.
            Storage::disk('local')->put("gre/{$nombre}.xml", $xml);
        } catch (Throwable $e) {
            return $this->fallo($viaje, 'No se pudo generar la guía', $e);
        }

        if (! $this->credencialesConfiguradas()) {
            return to_route('viajes.index')->with('toast', [
                'type' => 'warning',
                'message' => "Guía {$viaje->numero_gr} generada y firmada, pero no se envió: faltan las credenciales del Menú SOL.",
            ]);
        }

        try {
            $ticket = $this->enviador->enviar($nombre, $xml);
            $viaje->update(['gre_estado' => EstadoGre::Enviada, 'gre_ticket' => $ticket]);

            $resultado = $this->enviador->consultar($ticket);
        } catch (Throwable $e) {
            return $this->fallo($viaje, 'No se pudo enviar a SUNAT', $e);
        }

        $viaje->update([
            'gre_estado' => $resultado['aceptada'] ? EstadoGre::Aceptada : EstadoGre::Rechazada,
            'gre_respuesta' => $resultado,
        ]);

        return to_route('viajes.index')->with('toast', [
            'type' => $resultado['aceptada'] ? 'success' : 'error',
            'message' => $resultado['aceptada']
                ? "Guía {$viaje->numero_gr} aceptada por SUNAT."
                : "SUNAT rechazó la guía {$viaje->numero_gr}: [{$resultado['codigo']}] {$resultado['mensaje']}",
        ]);
    }

    private function fallo(Viaje $viaje, string $contexto, Throwable $e): RedirectResponse
    {
        $viaje->update([
            'gre_estado' => EstadoGre::Rechazada,
            'gre_respuesta' => ['aceptada' => false, 'codigo' => null, 'mensaje' => $e->getMessage(), 'cdr' => null],
        ]);

        return back()->with('toast', [
            'type' => 'error',
            'message' => "{$contexto} ({$viaje->numero_gr}): {$e->getMessage()}",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDelViaje(EmitirGuiaRequest $request): array
    {
        $cliente = Cliente::query()->findOrFail($request->integer('cliente_id'));
        $tracto = Vehiculo::query()->findOrFail($request->integer('tracto_id'));
        $carreta = $request->filled('carreta_id')
            ? Vehiculo::query()->find($request->integer('carreta_id'))
            : null;
        $conductor = Conductor::query()->findOrFail($request->integer('conductor_id'));

        $partida = $this->punto($request->validated('partida'));
        $llegada = $this->punto($request->validated('llegada'));

        return [
            'fecha_emision' => now(),
            'fecha_traslado' => $request->string('fecha_traslado')->value(),
            'origen' => $partida->direccion,
            'destino' => $llegada->direccion,
            'punto_partida_id' => $partida->id,
            'punto_llegada_id' => $llegada->id,
            'motivo_traslado' => $request->string('motivo_traslado')->value(),
            'cliente' => $cliente->razon_social,
            'cliente_ruc' => $cliente->ruc,
            'cliente_id' => $cliente->id,
            'destinatario' => Str::upper($request->string('destinatario')->value()),
            'destinatario_ruc' => $request->string('destinatario_ruc')->value(),
            'guias_remitente' => $request->validated('guias_remitente') ?? [],
            'peso' => $request->float('peso'),
            'unidad_peso' => $request->string('unidad_peso')->value(),
            'tipo_carga' => $request->string('tipo_carga')->value(),
            'placa_tracto' => $tracto->placa,
            'placa_carreta' => $carreta?->placa,
            'tracto_id' => $tracto->id,
            'carreta_id' => $carreta?->id,
            'conductor_nombre' => Str::upper("{$conductor->apellidos} {$conductor->nombres}"),
            'conductor_dni' => $conductor->documento,
            'conductor_id' => $conductor->id,
            'observaciones' => $request->string('observaciones')->value() ?: null,
        ];
    }

    /**
     * El lugar se reutiliza si ya existe con el mismo ubigeo y dirección; si
     * no, se guarda. Así el catálogo se llena emitiendo, sin una pantalla
     * aparte donde alguien tenga que cargarlo a mano.
     *
     * @param  array{ubigeo: string, direccion: string, nombre?: string|null}  $datos
     */
    private function punto(array $datos): PuntoTraslado
    {
        $direccion = Str::upper(trim($datos['direccion']));

        return PuntoTraslado::query()->firstOrCreate(
            ['ubigeo' => $datos['ubigeo'], 'direccion' => $direccion],
            [
                'nombre' => ($datos['nombre'] ?? null) ?: Str::limit($direccion, 40),
                'activo' => true,
            ],
        );
    }

    /**
     * @return array<int, array{value: string, label: string, tuc: string|null}>
     */
    private function vehiculos(TipoVehiculo $tipo): array
    {
        return Vehiculo::query()
            ->where('tipo', $tipo->value)
            ->orderBy('placa')
            ->get(['id', 'placa', 'tuc'])
            ->map(fn (Vehiculo $vehiculo): array => [
                'value' => (string) $vehiculo->id,
                'label' => $vehiculo->placa,
                'tuc' => $vehiculo->tuc,
            ])
            ->values()
            ->all();
    }

    private function emisorConfigurado(): bool
    {
        foreach (['ruc', 'razon_social', 'registro_mtc', 'direccion', 'ubigeo'] as $clave) {
            if (blank(config("gre.emisor.{$clave}"))) {
                return false;
            }
        }

        return true;
    }

    private function credencialesConfiguradas(): bool
    {
        foreach (['client_id', 'client_secret', 'usuario_sol', 'clave_sol'] as $clave) {
            if (blank(config("gre.api.{$clave}"))) {
                return false;
            }
        }

        return true;
    }
}
