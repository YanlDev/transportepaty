<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\EstadoValeCompra;
use App\Enums\FormaPago;
use App\Enums\MedioPago;
use App\Enums\Moneda;
use App\Enums\Permiso;
use App\Enums\SituacionValeCompra;
use App\Enums\TipoValeCompra;
use App\Enums\UnidadMedida;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\Almacen\ValeCompraRequest;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\CuentaBancaria;
use App\Models\DetalleValeCompra;
use App\Models\IngresoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\PagoCompra;
use App\Models\Proveedor;
use App\Models\UbicacionAlmacen;
use App\Models\ValeCompra;
use App\Models\Vehiculo;
use App\Rules\FechaDelAlmacen;
use App\Services\AlertasDeTaller;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\CodigoQr;
use App\Services\FileUploadService;
use App\Services\FirmaDeVales;
use App\Services\PdfDelPedido;
use App\Services\RecepcionDeCompra;
use App\Services\RelojOperativo;
use App\Services\ReposicionesDeEquipamiento;
use App\Services\RepuestosDeOrden;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los pedidos de compra: se piden (desde el almacén o desde una orden de
 * trabajo), gerencia los aprueba y, aprobados, salen firmados en PDF con su
 * QR. La compra entra después al almacén nombrando su pedido.
 */
class ValeCompraController extends Controller
{
    use CatalogosAlmacen;

    public function __construct(
        private readonly FirmaDeVales $firma,
        private readonly FileUploadService $subidas,
        private readonly AlertasDeTaller $alertas,
        private readonly RecepcionDeCompra $recepcion,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ValeCompra::class);

        $estado = EstadoValeCompra::tryFrom($request->string('estado')->value());

        $vales = ValeCompra::query()
            ->with(['proveedor:id,razon_social,nombre_comercial', 'solicitante:id,name', 'vehiculo:id,placa', 'detalles'])
            ->when($estado !== null, fn ($query) => $query->vigentes()->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ValeCompra $vale): array => $this->cabecera($vale));

        return Inertia::render('almacen/compras/index', [
            // Lo aprobado que todavía se le debe al proveedor, para quien
            // anota los pagos: lo que vence primero, arriba.
            'porPagar' => ($request->user()?->checkPermissionTo(Permiso::ComprasPagar) ?? false) ? self::porPagar() : null,
            'vales' => $vales,
            'filtros' => ['estado' => $estado?->value],
            'estados' => EstadoValeCompra::options(),
            'porAprobar' => ValeCompra::query()->vigentes()->where('estado', EstadoValeCompra::Solicitado)->count(),
            'puedeSolicitar' => $request->user()?->can('create', ValeCompra::class) ?? false,
        ]);
    }

    public function create(Request $request, RepuestosDeOrden $repuestos, ReposicionesDeEquipamiento $reposiciones): Response
    {
        $this->authorize('create', ValeCompra::class);

        // Desde una orden de trabajo se llega con la orden ya elegida.
        $orden = OrdenTrabajo::query()->vigentes()->find($request->integer('orden'));

        // O desde una reposición de equipamiento, con la unidad que lo pide.
        $unidad = $orden === null ? Vehiculo::query()->find($request->integer('reposicion')) : null;

        return Inertia::render('almacen/compras/form', [
            'vale' => null,
            'inicial' => [
                'tipo' => TipoValeCompra::Normal->value,
                'proveedor_id' => null,
                'moneda' => Moneda::Soles->value,
                'forma_pago' => FormaPago::Contado->value,
                'dias_credito' => null,
                'directo_a_unidad' => false,
                'motivo' => match (true) {
                    $orden !== null => "Para la {$orden->numero()}: {$orden->trabajo}",
                    $unidad !== null => "Reposición de equipamiento de {$unidad->placa}",
                    default => '',
                },
                'sustento_precio' => '',
                'orden_trabajo_id' => $orden?->id,
                'vehiculo_id' => $orden !== null ? $orden->vehiculo_id : $unidad?->id,
                // Nace con lo que falta y el stock no cubre.
                'detalles' => array_map(
                    fn (array $linea): array => ['es_servicio' => false, 'articulo_id' => $linea['articulo_id'], 'descripcion' => null, 'cantidad' => $linea['cantidad'], 'precio_unitario' => null],
                    match (true) {
                        $orden !== null => $repuestos->faltanteParaComprar($orden),
                        $unidad !== null => $reposiciones->porComprar($unidad),
                        default => [],
                    },
                ),
            ],
            ...$this->catalogos(),
        ]);
    }

    public function store(ValeCompraRequest $request): RedirectResponse
    {
        $this->authorize('create', ValeCompra::class);

        $vale = DB::transaction(function () use ($request): ValeCompra {
            $vale = ValeCompra::create([
                ...$this->datosDe($request),
                'estado' => EstadoValeCompra::Solicitado,
                'codigo_publico' => ValeCompra::nuevoCodigoPublico(),
                'codigo_verificacion' => ValeCompra::nuevoCodigoVerificacion(),
                'solicitado_por' => $request->user()?->id,
            ]);

            $this->guardarLineas($vale, $request);

            return $vale;
        });

        $this->alertas->pedidoPorAprobar($vale, $request->user());

        return to_route('almacen.compras.show', $vale)
            ->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} pedido: queda por aprobar."]);
    }

    public function show(Request $request, ValeCompra $vale, CodigoQr $qr): Response
    {
        $this->authorize('view', $vale);

        $vale->load([
            'proveedor', 'solicitante:id,name', 'aprobador:id,name', 'rechazadoPor:id,name', 'anuladoPor:id,name',
            'ordenTrabajo', 'vehiculo:id,placa', 'detalles.articulo', 'media',
        ]);

        $usuario = $request->user();
        $veParciales = $usuario?->checkPermissionTo('compras.parciales') ?? false;
        $situacion = $vale->situacion();

        return Inertia::render('almacen/compras/show', [
            'vale' => [
                ...$this->cabecera($vale),
                'motivo' => $vale->motivo,
                'sustento_precio' => $vale->sustento_precio,
                'codigo_verificacion' => $vale->codigo_verificacion,
                'url_verificacion' => route('vales.verificar', $vale->codigo_publico),
                'firma_valida' => $vale->estado === EstadoValeCompra::Aprobado ? $this->firma->esAutentico($vale) : null,
                'aprobado_por' => $vale->aprobador?->name,
                'aprobado_at' => $vale->aprobado_at?->toIso8601String(),
                'rechazado_por' => $vale->rechazadoPor?->name,
                'motivo_rechazo' => $vale->motivo_rechazo,
                'anulado_por' => $vale->anuladoPor?->name,
                'motivo_anulacion' => $vale->motivo_anulacion,
                'recibido' => $vale->recibido(),
                'saldo' => $vale->saldo(),
                // Las entregas parciales solo existen para quien puede activarlas.
                'permite_parciales' => $veParciales ? $vale->permite_parciales : null,
                'orden' => $vale->ordenTrabajo === null ? null : [
                    'id' => $vale->ordenTrabajo->id,
                    'numero' => $vale->ordenTrabajo->numero(),
                    'trabajo' => $vale->ordenTrabajo->trabajo,
                ],
                'detalles' => $vale->detalles->map(fn (DetalleValeCompra $detalle): array => [
                    'id' => $detalle->id,
                    'articulo_id' => $detalle->articulo_id,
                    'codigo' => $detalle->articulo?->codigo,
                    'descripcion' => $detalle->descripcion,
                    'es_servicio' => $detalle->es_servicio,
                    'unidad' => $detalle->articulo === null ? 'serv' : ($detalle->articulo->unidad_compra ?? $detalle->articulo->unidad)->abreviatura(),
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'total' => (float) $detalle->total,
                    'atendido_at' => $detalle->atendido_at?->toIso8601String(),
                ])->values()->all(),
                'cotizaciones' => $vale->getMedia('cotizaciones')->map(fn (Media $archivo): array => [
                    'id' => $archivo->id,
                    'nombre' => $archivo->file_name,
                    'url' => route('almacen.compras.archivo', [$vale, $archivo]),
                ])->values()->all(),
                'ingresos' => $vale->ingresos()->orderBy('id')->get()->map(fn (IngresoAlmacen $ingreso): array => [
                    'id' => $ingreso->id,
                    'numero' => $ingreso->numero(),
                    'fecha' => $ingreso->fecha->toDateString(),
                    'documento' => $ingreso->documento(),
                    'total' => (float) $ingreso->total,
                    'anulado' => $ingreso->estaAnulado(),
                ])->values()->all(),
                'consultas' => $vale->consultas()->count(),
                'directo_a_unidad' => $vale->directo_a_unidad,
                // Lo que falta recibir, para recibirlo apenas llega.
                'por_recibir' => $this->recepcion->sePuedeRecibir($vale) ? $this->recepcion->pendientes($vale) : [],
                // Lo que se le pagó al proveedor y lo que falta.
                'pago' => $vale->estado !== EstadoValeCompra::Aprobado ? null : [
                    'pagado' => $vale->pagado(),
                    'por_pagar' => $vale->porPagar(),
                    'vence_el' => $vale->pagoVenceEl()?->toDateString(),
                    'vencido' => $vale->tienePagoPendiente() && ($vale->pagoVenceEl()?->lt(RelojOperativo::fechaDeHoy()) ?? false),
                    'cuenta_proveedor' => $vale->proveedor->cuenta_pago,
                    'pagos' => $vale->pagos()->with(['cuenta:id,banco,alias', 'user:id,name'])->get()->map(fn (PagoCompra $pago): array => [
                        'id' => $pago->id,
                        'fecha' => $pago->fecha->toDateString(),
                        'monto' => (float) $pago->monto,
                        'medio' => $pago->medio->label(),
                        'cuenta' => $pago->cuenta === null ? null : "{$pago->cuenta->banco} · {$pago->cuenta->alias}",
                        'numero_operacion' => $pago->numero_operacion,
                        'voucher_url' => $pago->voucher_id === null ? null : route('almacen.compras.archivo', [$vale, $pago->voucher_id]),
                        'registro' => $pago->user?->name,
                        'anulado' => $pago->estaAnulado(),
                        'motivo_anulacion' => $pago->motivo_anulacion,
                    ])->values()->all(),
                ],
            ],
            'medios' => MedioPago::options(),
            'cuentas' => ($usuario?->can('pagar', $vale) ?? false)
                ? CuentaBancaria::query()->where('activa', true)->orderBy('banco')->get(['id', 'banco', 'alias', 'moneda'])
                    ->map(fn (CuentaBancaria $cuenta): array => ['id' => $cuenta->id, 'nombre' => "{$cuenta->banco} · {$cuenta->alias}"])->all()
                : [],
            'hoy' => RelojOperativo::hoy(),
            'puede' => [
                'aprobar' => $usuario?->can('aprobar', $vale) ?? false,
                'pagar' => $usuario?->can('pagar', $vale) ?? false,
                'corregir' => $usuario?->can('update', $vale) ?? false,
                'anular' => $usuario?->can('anular', $vale) ?? false,
                'parciales' => $usuario?->can('parciales', $vale) ?? false,
                'confirmar_servicio' => ($usuario?->can('confirmarServicio', $vale) ?? false) && $situacion->sirveParaComprar(),
                'ingresar' => ($usuario?->can('create', IngresoAlmacen::class) ?? false) && $situacion->sirveParaComprar(),
                'imprimir' => $vale->estado === EstadoValeCompra::Aprobado && ! $vale->estaAnulado(),
            ],
            // El mismo QR del PDF, para mostrarlo o mandarlo desde el celular.
            'qr' => $vale->estado === EstadoValeCompra::Aprobado && ! $vale->estaAnulado()
                ? $qr->celdas(route('vales.verificar', $vale->codigo_publico))
                : null,
            'esPropio' => $usuario !== null && $vale->solicitado_por === $usuario->id,
            'maximoKb' => (int) config('uploads.imagenes.max_kb'),
        ]);
    }

    public function edit(ValeCompra $vale): Response
    {
        $this->authorize('update', $vale);

        $vale->load('detalles');

        return Inertia::render('almacen/compras/form', [
            'vale' => [
                'id' => $vale->id,
                'numero' => $vale->numero(),
                'aprobado' => $vale->estado === EstadoValeCompra::Aprobado,
                'cotizaciones' => $vale->getMedia('cotizaciones')->map(fn (Media $archivo): array => [
                    'id' => $archivo->id,
                    'nombre' => $archivo->file_name,
                    'url' => route('almacen.compras.archivo', [$vale, $archivo]),
                ])->values()->all(),
            ],
            'inicial' => [
                'tipo' => $vale->tipo->value,
                'proveedor_id' => $vale->proveedor_id,
                'moneda' => $vale->moneda->value,
                'forma_pago' => $vale->forma_pago->value,
                'dias_credito' => $vale->dias_credito,
                'directo_a_unidad' => $vale->directo_a_unidad,
                'motivo' => $vale->motivo,
                'sustento_precio' => $vale->sustento_precio ?? '',
                'orden_trabajo_id' => $vale->orden_trabajo_id,
                'vehiculo_id' => $vale->vehiculo_id,
                'detalles' => $vale->detalles->map(fn (DetalleValeCompra $detalle): array => [
                    'es_servicio' => $detalle->es_servicio,
                    'articulo_id' => $detalle->articulo_id,
                    'descripcion' => $detalle->es_servicio ? $detalle->descripcion : '',
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                ])->values()->all(),
            ],
            ...$this->catalogos(),
        ]);
    }

    /**
     * Corregir el pedido. Si ya estaba aprobado, la aprobación y la firma se
     * pierden: cambió lo que se autorizó, así que vuelve a quien aprueba.
     */
    public function update(ValeCompraRequest $request, ValeCompra $vale): RedirectResponse
    {
        $this->authorize('update', $vale);

        DB::transaction(function () use ($request, $vale): void {
            $vale->update([
                ...$this->datosDe($request),
                'estado' => EstadoValeCompra::Solicitado,
                'aprobado_por' => null,
                'aprobado_at' => null,
                'vence_el' => null,
                'huella' => null,
                'firma' => null,
                'rechazado_por' => null,
                'rechazado_at' => null,
                'motivo_rechazo' => null,
            ]);

            $vale->detalles()->delete();
            // Solo las suyas: un id de otro pedido no quita nada.
            $vale->getMedia('cotizaciones')
                ->whereIn('id', array_map(intval(...), $request->validated('quitar_cotizaciones') ?? []))
                ->each(fn (Media $archivo) => $archivo->delete());
            $this->guardarLineas($vale, $request);
        });

        $this->alertas->pedidoPorAprobar($vale->refresh(), $request->user());

        return to_route('almacen.compras.show', $vale)
            ->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} corregido: vuelve a aprobación."]);
    }

    /**
     * Aprobar firma el pedido y le pone vencimiento. Nadie aprueba lo que él
     * mismo pidió.
     */
    public function aprobar(Request $request, ValeCompra $vale): RedirectResponse
    {
        $this->authorize('aprobar', $vale);

        if ($vale->solicitado_por === $request->user()?->id) {
            throw ValidationException::withMessages(['firma' => 'No puedes aprobar una compra que pediste tú: la aprueba otra persona.']);
        }

        if ($vale->tipo === TipoValeCompra::Normal && blank($vale->sustento_precio) && $vale->getMedia('cotizaciones')->isEmpty()) {
            throw ValidationException::withMessages(['firma' => 'El pedido no tiene cotización ni sustento del precio: pide que lo completen antes de aprobarlo.']);
        }

        DB::transaction(function () use ($request, $vale): void {
            $vale->update([
                'estado' => EstadoValeCompra::Aprobado,
                'aprobado_por' => $request->user()?->id,
                'aprobado_at' => now()->startOfSecond(),
                // Un pedido aprobado vale hasta que se use o se anule.
                'vence_el' => null,
            ]);

            $this->firma->firmar($vale);

            // Una emergencia ya instalada en la unidad entra y sale sola.
            $this->recepcion->directoALaUnidad($vale->refresh(), $request->user());
        });

        $this->alertas->pedidoResuelto($vale, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$vale->numero()} aprobado: ya se puede pagar y comprar.",
        ]);
    }

    public function rechazar(AnularDocumentoRequest $request, ValeCompra $vale): RedirectResponse
    {
        $this->authorize('aprobar', $vale);

        $vale->update([
            'estado' => EstadoValeCompra::Rechazado,
            'rechazado_por' => $request->user()?->id,
            'rechazado_at' => now(),
            'motivo_rechazo' => $request->validated('motivo'),
        ]);

        $this->alertas->pedidoResuelto($vale, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} rechazado."]);
    }

    public function anular(AnularDocumentoRequest $request, ValeCompra $vale): RedirectResponse
    {
        $this->authorize('anular', $vale);

        $vale->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $request->validated('motivo'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} anulado: ya no sirve para comprar."]);
    }

    /**
     * Dejar que el pedido se use en varias compras hasta completar su monto.
     */
    public function parciales(Request $request, ValeCompra $vale): RedirectResponse
    {
        $this->authorize('parciales', $vale);

        $vale->update(['permite_parciales' => $request->boolean('permite_parciales')]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $vale->permite_parciales
                ? "{$vale->numero()} admite compras en partes."
                : "{$vale->numero()} vuelve a ser de una sola compra.",
        ]);
    }

    /**
     * El tercero hizo el servicio: con su comprobante, la línea pasa a ser
     * costo de la orden de trabajo del pedido.
     */
    public function confirmarServicio(Request $request, ValeCompra $vale, DetalleValeCompra $detalle): RedirectResponse
    {
        $this->authorize('confirmarServicio', $vale);

        abort_unless($detalle->vale_compra_id === $vale->id && $detalle->es_servicio, 404);

        $request->validate(
            ['comprobante' => ['required', ...ReglasDeArchivo::imagenOPdf()]],
            ['comprobante.required' => 'Adjunta el comprobante del servicio.'],
        );

        if ($detalle->atendido_at !== null) {
            throw ValidationException::withMessages(['comprobante' => 'Ese servicio ya se confirmó.']);
        }

        if (! $vale->situacion()->sirveParaComprar()) {
            throw ValidationException::withMessages(['comprobante' => "El pedido está {$this->enMinuscula($vale->situacion())}: ya no sirve para pagar el servicio."]);
        }

        DB::transaction(function () use ($request, $vale, $detalle): void {
            $detalle->update(['atendido_at' => now(), 'atendido_por' => $request->user()?->id]);

            $this->subidas->guardar($vale, $request->file('comprobante'), 'comprobantes_servicio', 'comprobante');
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Servicio confirmado: ya cuenta en el costo de la orden.']);
    }

    /**
     * El pedido aprobado, para imprimir o mandar: con su QR, su código y los
     * nombres de quien lo pidió y quien lo aprobó.
     */
    public function pdf(ValeCompra $vale, PdfDelPedido $pdf): HttpResponse
    {
        $this->authorize('view', $vale);

        abort_unless($vale->estado === EstadoValeCompra::Aprobado && ! $vale->estaAnulado(), 404);

        return $pdf->documento($vale)->download($pdf->nombre($vale));
    }

    /**
     * Una cotización o un comprobante de servicio del pedido. Están en el disco
     * privado: solo se abren por acá.
     */
    public function archivo(Request $request, ValeCompra $vale, Media $archivo): StreamedResponse
    {
        $this->authorize('view', $vale);

        abort_unless($archivo->model_type === $vale->getMorphClass() && (int) $archivo->model_id === $vale->id, 404);

        return $archivo->toInlineResponse($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDe(ValeCompraRequest $request): array
    {
        $datos = $request->validated();
        $orden = isset($datos['orden_trabajo_id']) ? OrdenTrabajo::query()->find((int) $datos['orden_trabajo_id']) : null;

        return [
            'tipo' => $datos['tipo'],
            'proveedor_id' => $datos['proveedor_id'],
            'moneda' => $datos['moneda'],
            'forma_pago' => $datos['forma_pago'] ?? FormaPago::Contado->value,
            'dias_credito' => ($datos['forma_pago'] ?? null) === FormaPago::Credito->value ? (int) $datos['dias_credito'] : null,
            'motivo' => $datos['motivo'],
            'sustento_precio' => $datos['sustento_precio'] ?? null,
            'directo_a_unidad' => $datos['tipo'] === TipoValeCompra::Emergencia->value && (bool) ($datos['directo_a_unidad'] ?? false),
            'orden_trabajo_id' => $orden?->id,
            // La orden ya dice de qué unidad es.
            'vehiculo_id' => $orden !== null ? $orden->vehiculo_id : ($datos['vehiculo_id'] ?? null),
        ];
    }

    /**
     * Las líneas, el total que dan y las cotizaciones que llegan.
     */
    private function guardarLineas(ValeCompra $vale, ValeCompraRequest $request): void
    {
        /** @var list<array{es_servicio?: mixed, articulo_id?: int|string|null, descripcion?: string|null, cantidad: int|float|string, precio_unitario: int|float|string}> $lineas */
        $lineas = $request->validated('detalles');
        $articulos = Articulo::query()->whereIn('id', array_filter(array_column($lineas, 'articulo_id')))->get()->keyBy('id');
        $total = 0.0;

        foreach ($lineas as $linea) {
            $esServicio = filter_var($linea['es_servicio'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $articulo = $esServicio ? null : $articulos->get((int) ($linea['articulo_id'] ?? 0));
            $importe = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
            $total += $importe;

            $vale->detalles()->create([
                'articulo_id' => $articulo?->id,
                'descripcion' => $articulo === null ? (string) ($linea['descripcion'] ?? '') : $articulo->nombre,
                'es_servicio' => $esServicio,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'total' => $importe,
            ]);
        }

        $vale->forceFill(['total' => round($total, 2)])->save();

        foreach ($request->file('cotizaciones', []) as $indice => $archivo) {
            $this->subidas->guardar($vale, $archivo, 'cotizaciones', "cotizaciones.{$indice}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(ValeCompra $vale): array
    {
        $situacion = $vale->situacion();

        return [
            'id' => $vale->id,
            'numero' => $vale->numero(),
            'tipo' => $vale->tipo->value,
            'tipo_label' => $vale->tipo->label(),
            'estado' => $vale->estado->value,
            'situacion' => $situacion->value,
            'situacion_label' => $situacion->label(),
            'proveedor' => $vale->proveedor->nombre(),
            'proveedor_ruc' => $vale->proveedor->ruc,
            'moneda' => $vale->moneda->value,
            'simbolo' => $vale->moneda->simbolo(),
            'total' => (float) $vale->total,
            'placa' => $vale->vehiculo?->placa,
            'orden_trabajo_id' => $vale->orden_trabajo_id,
            'solicitado_por' => $vale->solicitante?->name,
            'solicitado' => $vale->created_at?->toIso8601String(),
            'vence_el' => null,
            'forma_pago' => $vale->forma_pago->label().($vale->forma_pago === FormaPago::Credito ? " a {$vale->dias_credito} días" : ''),
        ];
    }

    /**
     * Llegó la mercadería: entra al stock con los precios del pedido. El
     * comprobante se completa después, desde la recepción.
     */
    public function recibir(Request $request, ValeCompra $vale): RedirectResponse
    {
        $this->authorize('create', IngresoAlmacen::class);

        $datos = $request->validate([
            'fecha' => ['required', 'date', new FechaDelAlmacen],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.articulo_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'tipo_cambio' => [Rule::requiredIf($vale->moneda === Moneda::Dolares), 'nullable', 'numeric', 'gt:0', 'max:99'],
        ], ['tipo_cambio.required' => 'El pedido es en dólares: pon el tipo de cambio del día.']);

        $ingreso = DB::transaction(fn (): IngresoAlmacen => $this->recepcion->recibir(
            $vale,
            array_map(fn (array $linea): array => ['articulo_id' => (int) $linea['articulo_id'], 'cantidad' => (float) $linea['cantidad']], array_values($datos['detalles'])),
            $datos['fecha'],
            $request->user(),
            $datos['observacion'] ?? null,
            isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null,
        ));

        return to_route('almacen.ingresos.show', $ingreso)->with('toast', [
            'type' => 'success',
            'message' => "Recepción {$ingreso->numero()} registrada: el stock ya entró. Falta completar el comprobante.",
        ]);
    }

    /**
     * Los pedidos aprobados con saldo por pagar, del que vence primero.
     *
     * @return list<array{id: int, numero: string, proveedor: string, simbolo: string, total: float, por_pagar: float, forma_pago: string, vence_el: string|null, vencido: bool}>
     */
    public static function porPagar(): array
    {
        $hoy = RelojOperativo::fechaDeHoy();

        return ValeCompra::query()
            ->vigentes()
            ->where('estado', EstadoValeCompra::Aprobado)
            ->with('proveedor')
            ->orderBy('id')
            ->get()
            ->filter(fn (ValeCompra $vale): bool => $vale->tienePagoPendiente())
            ->map(fn (ValeCompra $vale): array => [
                'id' => $vale->id,
                'numero' => $vale->numero(),
                'proveedor' => $vale->proveedor->nombre(),
                'simbolo' => $vale->moneda->simbolo(),
                'total' => (float) $vale->total,
                'por_pagar' => $vale->porPagar(),
                'forma_pago' => $vale->forma_pago->label(),
                'vence_el' => $vale->pagoVenceEl()?->toDateString(),
                'vencido' => $vale->pagoVenceEl()?->lt($hoy) ?? false,
            ])
            ->sortBy('vence_el')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'tipos' => TipoValeCompra::options(),
            'monedas' => Moneda::options(),
            'proveedores' => $this->proveedoresParaElegir(),
            'puedeCrearProveedor' => request()->user()?->can('create', Proveedor::class) ?? false,
            'articulos' => $this->articulosParaElegir(false),
            // Un repuesto que nunca se compró se da de alta sin salir del pedido.
            'puedeCrearArticulo' => request()->user()?->can('create', Articulo::class) ?? false,
            'categorias' => CategoriaArticulo::query()->orderBy('nombre')->get(['id', 'nombre']),
            'ubicaciones' => UbicacionAlmacen::query()->orderBy('nombre')->get(['id', 'nombre']),
            'unidades' => array_map(
                fn (UnidadMedida $unidad): array => ['value' => $unidad->value, 'label' => "{$unidad->label()} ({$unidad->abreviatura()})"],
                UnidadMedida::cases(),
            ),
            'vehiculos' => $this->vehiculosParaElegir(),
            'ordenes' => $this->ordenesParaElegir(),
            'maximoKb' => (int) config('uploads.imagenes.max_kb'),
        ];
    }

    private function enMinuscula(SituacionValeCompra $situacion): string
    {
        return mb_strtolower($situacion->label());
    }
}
