<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\EstadoValeCompra;
use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Enums\TipoIngreso;
use App\Enums\UnidadMedida;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\Almacen\StoreIngresoRequest;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\DetalleIngreso;
use App\Models\DetalleValeCompra;
use App\Models\IngresoAlmacen;
use App\Models\Proveedor;
use App\Models\UbicacionAlmacen;
use App\Models\ValeCompra;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\CostoDeCompra;
use App\Services\FileUploadService;
use App\Services\Kardex;
use App\Services\PeriodoAlmacen;
use App\Services\RelojOperativo;
use App\Services\UsoDeVale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que entra al almacén: las compras con su comprobante y las devoluciones
 * de los vales.
 */
class IngresoAlmacenController extends Controller
{
    use CatalogosAlmacen;

    public function __construct(
        private readonly Kardex $kardex,
        private readonly CostoDeCompra $costoDeCompra,
        private readonly FileUploadService $subidas,
        private readonly UsoDeVale $usoDeVale,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IngresoAlmacen::class);

        $veCostos = $this->veCostos($request->user());
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'proveedor' => $request->string('proveedor')->value(),
            'tipo' => $request->string('tipo')->value(),
        ];

        $ingresos = IngresoAlmacen::query()
            ->with(['proveedor', 'salida', 'user:id,name'])
            ->withCount('detalles')
            ->when($filtros['buscar'] !== '', function ($query) use ($filtros): void {
                $texto = $filtros['buscar'];
                $id = (int) preg_replace('/\D/', '', $texto);

                $query->where(fn ($query) => $query
                    ->where('serie_numero', 'like', '%'.mb_strtoupper($texto).'%')
                    ->orWhereIn('proveedor_id', Proveedor::query()->buscar($texto)->select('id'))
                    ->when($id > 0, fn ($query) => $query->orWhere('id', $id)));
            })
            ->when($filtros['proveedor'] !== '', fn ($query) => $query->where('proveedor_id', (int) $filtros['proveedor']))
            ->when($filtros['tipo'] !== '', fn ($query) => $query->where('tipo', $filtros['tipo']))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (IngresoAlmacen $ingreso): array => [
                ...$this->cabecera($ingreso, $veCostos),
                'lineas' => $ingreso->detalles_count,
            ]);

        return Inertia::render('almacen/ingresos/index', [
            'ingresos' => $ingresos,
            'filtros' => $filtros,
            'proveedores' => $this->proveedoresParaElegir(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', IngresoAlmacen::class);

        return Inertia::render('almacen/ingresos/create', [
            'proveedores' => $this->proveedoresParaElegir(),
            'articulos' => $this->articulosParaElegir($this->veCostos($request->user())),
            'comprobantes' => TipoComprobante::options(),
            'monedas' => Moneda::options(),
            'hoy' => RelojOperativo::hoy(),
            // Los vales con que todavía se puede comprar, y desde qué fecha
            // de comprobante el vale es obligatorio.
            'vales' => $this->valesParaElegir(),
            'valeInicial' => $request->integer('vale') ?: null,
            'valeObligatorioDesde' => UsoDeVale::obligatorioDesde()?->toDateString(),
            'tasaIgv' => CostoDeCompra::TASA_IGV,
            'toleranciaTotal' => StoreIngresoRequest::TOLERANCIA_TOTAL,
            'inicioPeriodo' => PeriodoAlmacen::inicio()->toDateString(),
            'maximoKb' => (int) config('uploads.imagenes.max_kb'),
            'puedeCrearArticulo' => $request->user()?->can('create', Articulo::class) ?? false,
            'puedeCrearProveedor' => $request->user()?->can('create', Proveedor::class) ?? false,
            'categorias' => CategoriaArticulo::query()->orderBy('nombre')->get(['id', 'nombre']),
            'ubicaciones' => UbicacionAlmacen::query()->orderBy('nombre')->get(['id', 'nombre']),
            'unidades' => array_map(
                fn (UnidadMedida $unidad): array => ['value' => $unidad->value, 'label' => "{$unidad->label()} ({$unidad->abreviatura()})"],
                UnidadMedida::cases(),
            ),
        ]);
    }

    public function store(StoreIngresoRequest $request): RedirectResponse
    {
        $this->authorize('create', IngresoAlmacen::class);

        $datos = $request->validated();
        $comprobante = TipoComprobante::from($datos['tipo_comprobante']);
        $moneda = Moneda::from($datos['moneda']);
        $articulos = Articulo::query()->whereIn('id', array_column($datos['detalles'], 'articulo_id'))->get()->keyBy('id');

        $lineas = array_values(array_map(fn (array $linea): array => [
            'cantidad' => (float) $linea['cantidad'],
            'precio_unitario' => (float) $linea['precio_unitario'],
            'factor' => $articulos[$linea['articulo_id']]->factor(),
            'bonificacion' => (bool) ($linea['bonificacion'] ?? false),
        ], $datos['detalles']));

        $calculo = $this->costoDeCompra->calcular(
            $comprobante,
            $moneda,
            isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null,
            $request->boolean('precios_con_igv'),
            $lineas,
            (float) ($datos['descuento'] ?? 0),
            (float) ($datos['flete'] ?? 0),
        );

        // Se guarda el total del papel; el IGV es lo que va del subtotal a ese
        // total (absorbe los céntimos del redondeo por línea del proveedor).
        $total = round((float) $datos['total_comprobante'], 2);

        $ingreso = DB::transaction(function () use ($request, $datos, $comprobante, $moneda, $lineas, $calculo, $total): IngresoAlmacen {
            // El vale se bloquea y se vuelve a comprobar acá adentro: dos
            // compras a la vez con el mismo vale no pueden entrar las dos.
            if (isset($datos['vale_compra_id'])) {
                $vale = ValeCompra::query()->lockForUpdate()->with(['detalles', 'proveedor'])->findOrFail((int) $datos['vale_compra_id']);
                $error = $this->usoDeVale->error($vale, (int) $datos['proveedor_id'], $moneda, $datos['fecha_emision'], $total, $request->lineasParaElVale());

                if ($error !== null) {
                    throw ValidationException::withMessages(['vale_compra_id' => $error]);
                }
            }

            $ingreso = IngresoAlmacen::create([
                'tipo' => TipoIngreso::Compra,
                'fecha' => $datos['fecha'],
                'fecha_emision' => $datos['fecha_emision'],
                'proveedor_id' => $datos['proveedor_id'],
                'vale_compra_id' => $datos['vale_compra_id'] ?? null,
                'tipo_comprobante' => $comprobante,
                'serie_numero' => $datos['serie_numero'] ?? null,
                'comprobante_clave' => IngresoAlmacen::claveComprobante($datos['serie_numero'] ?? null),
                'moneda' => $moneda,
                'tipo_cambio' => $moneda === Moneda::Dolares ? $datos['tipo_cambio'] : null,
                'precios_con_igv' => $request->boolean('precios_con_igv'),
                'descuento' => (float) ($datos['descuento'] ?? 0),
                'flete' => (float) ($datos['flete'] ?? 0),
                'subtotal' => $calculo['subtotal'],
                'igv' => $comprobante->llevaIgv() ? round($total - $calculo['subtotal'], 2) : 0,
                'total' => $total,
                'costo_total' => $calculo['costo_total'],
                'observacion' => $datos['observacion'] ?? null,
                'user_id' => $request->user()?->id,
            ]);

            foreach ($datos['detalles'] as $i => $linea) {
                $ingreso->detalles()->create([
                    'articulo_id' => $linea['articulo_id'],
                    'bonificacion' => $lineas[$i]['bonificacion'],
                    'cantidad' => $lineas[$i]['cantidad'],
                    'precio_unitario' => $lineas[$i]['precio_unitario'],
                    'factor' => $lineas[$i]['factor'],
                    'vence_el' => $linea['vence_el'] ?? null,
                    ...$calculo['lineas'][$i],
                ]);
            }

            $this->kardex->registrarIngreso($ingreso, $request->user()?->id);

            // Al final: si algo falla antes, no queda un archivo suelto en el disco.
            $this->subidas->guardar($ingreso, $request->file('comprobante'), 'comprobante', 'comprobante');

            return $ingreso;
        });

        return to_route('almacen.ingresos.show', $ingreso)
            ->with('toast', ['type' => 'success', 'message' => "Ingreso {$ingreso->numero()} registrado: el stock ya se actualizó."]);
    }

    /**
     * Los vales aprobados que todavía sirven para comprar, con sus líneas
     * para precargar el ingreso.
     *
     * @return list<array<string, mixed>>
     */
    private function valesParaElegir(): array
    {
        return array_values(ValeCompra::query()
            ->vigentes()
            ->where('estado', EstadoValeCompra::Aprobado)
            ->with(['detalles', 'proveedor'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn (ValeCompra $vale): bool => $vale->situacion()->sirveParaComprar() && $vale->totalArticulos() > 0)
            ->map(fn (ValeCompra $vale): array => [
                'id' => $vale->id,
                'numero' => $vale->numero(),
                'proveedor_id' => $vale->proveedor_id,
                'proveedor' => $vale->proveedor->nombre(),
                'moneda' => $vale->moneda->value,
                'total' => $vale->totalArticulos(),
                'vence_el' => $vale->vence_el?->toDateString(),
                'detalles' => $vale->detalles->where('es_servicio', false)->map(fn (DetalleValeCompra $detalle): array => [
                    'articulo_id' => $detalle->articulo_id,
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                ])->values()->all(),
            ])
            ->all());
    }

    /**
     * Cambiar la foto o el PDF (se subió el equivocado o salió borroso). El
     * anterior se borra: la colección guarda uno solo.
     */
    public function reemplazarComprobante(Request $request, IngresoAlmacen $ingreso): RedirectResponse
    {
        $this->authorize('reemplazarComprobante', $ingreso);

        $request->validate([
            'comprobante' => ['required', ...ReglasDeArchivo::imagenOPdf()],
        ], (new StoreIngresoRequest)->messages());

        $this->subidas->guardar($ingreso, $request->file('comprobante'), 'comprobante', 'comprobante');

        return back()->with('toast', ['type' => 'success', 'message' => 'Comprobante reemplazado.']);
    }

    public function show(Request $request, IngresoAlmacen $ingreso): Response
    {
        $this->authorize('view', $ingreso);

        $veCostos = $this->veCostos($request->user());
        $ingreso->load(['proveedor', 'salida', 'user:id,name', 'anuladoPor:id,name', 'detalles.articulo', 'media']);
        $archivo = $ingreso->getFirstMedia('comprobante');

        return Inertia::render('almacen/ingresos/show', [
            'ingreso' => [
                ...$this->cabecera($ingreso, $veCostos),
                'tipo_cambio' => $ingreso->tipo_cambio === null ? null : (float) $ingreso->tipo_cambio,
                'precios_con_igv' => $ingreso->precios_con_igv,
                'fecha_emision' => $ingreso->fecha_emision?->toDateString(),
                'descuento' => $this->costo($veCostos, $ingreso->descuento),
                'flete' => $this->costo($veCostos, $ingreso->flete),
                'subtotal' => $this->costo($veCostos, $ingreso->subtotal),
                'igv' => $this->costo($veCostos, $ingreso->igv),
                'observacion' => $ingreso->observacion,
                'anulado_por' => $ingreso->anuladoPor?->name,
                'motivo_anulacion' => $ingreso->motivo_anulacion,
                'comprobante' => $archivo === null ? null : [
                    'nombre' => $archivo->name.'.'.$archivo->extension,
                    'url' => route('almacen.ingresos.comprobante', $ingreso),
                    'es_pdf' => $archivo->mime_type === 'application/pdf',
                    'peso_kb' => (int) ceil($archivo->size / 1024),
                ],
                'proveedor_detalle' => $ingreso->proveedor === null ? null : [
                    'ruc' => $ingreso->proveedor->ruc,
                    'razon_social' => $ingreso->proveedor->razon_social,
                ],
                'salida_id' => $ingreso->salida_almacen_id,
                'detalles' => $ingreso->detalles->map(fn (DetalleIngreso $detalle): array => [
                    'id' => $detalle->id,
                    'articulo_id' => $detalle->articulo_id,
                    'codigo' => $detalle->articulo->codigo,
                    'nombre' => $detalle->articulo->nombre,
                    'unidad' => $detalle->articulo->unidad->abreviatura(),
                    'unidad_compra' => (float) $detalle->factor !== 1.0 ? $detalle->articulo->unidad_compra?->abreviatura() : null,
                    'bonificacion' => $detalle->bonificacion,
                    'vence_el' => $detalle->vence_el?->toDateString(),
                    'cantidad' => (float) $detalle->cantidad,
                    'cantidad_base' => (float) $detalle->cantidad_base,
                    'precio_unitario' => $this->costo($veCostos, $detalle->precio_unitario),
                    'costo_unitario' => $this->costo($veCostos, $detalle->costo_unitario),
                    'costo_total' => $this->costo($veCostos, $detalle->costo_total),
                ])->values()->all(),
            ],
            'puedeAnular' => $request->user()?->can('anular', $ingreso) ?? false,
            'periodoAbierto' => PeriodoAlmacen::estaAbierto($ingreso->fecha),
            'puedeReemplazarComprobante' => $request->user()?->can('reemplazarComprobante', $ingreso) ?? false,
            'maximoKb' => (int) config('uploads.imagenes.max_kb'),
        ]);
    }

    /**
     * El PDF o la foto del comprobante, para verlo sin descargarlo.
     */
    public function comprobante(Request $request, IngresoAlmacen $ingreso): StreamedResponse
    {
        $this->authorize('view', $ingreso);

        $archivo = $ingreso->getFirstMedia('comprobante');
        abort_if(! $archivo instanceof Media, 404);

        return $archivo->toInlineResponse($request);
    }

    public function anular(AnularDocumentoRequest $request, IngresoAlmacen $ingreso): RedirectResponse
    {
        $this->authorize('anular', $ingreso);
        PeriodoAlmacen::exigirAbierto($ingreso->fecha);

        DB::transaction(function () use ($request, $ingreso): void {
            $ingreso->update([
                'anulado_at' => now(),
                'anulado_por' => $request->user()?->id,
                'motivo_anulacion' => $request->validated('motivo'),
            ]);

            $this->kardex->anular($ingreso);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "Ingreso {$ingreso->numero()} anulado: salió del kardex."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(IngresoAlmacen $ingreso, bool $veCostos): array
    {
        return [
            'id' => $ingreso->id,
            'numero' => $ingreso->numero(),
            'tipo' => $ingreso->tipo->value,
            'tipo_label' => $ingreso->tipo->label(),
            'fecha' => $ingreso->fecha->toDateString(),
            'proveedor' => $ingreso->proveedor?->nombre(),
            'proveedor_id' => $ingreso->proveedor_id,
            'vale_compra_id' => $ingreso->vale_compra_id,
            'vale_compra' => $ingreso->vale_compra_id === null ? null : sprintf('VC-%06d', $ingreso->vale_compra_id),
            'comprobante_tipo' => $ingreso->tipo_comprobante?->label(),
            'serie_numero' => $ingreso->serie_numero,
            'documento' => $ingreso->documento(),
            'moneda' => $ingreso->moneda->value,
            'simbolo' => $ingreso->moneda->simbolo(),
            'total' => $this->costo($veCostos, $ingreso->total),
            'costo_total' => $this->costo($veCostos, $ingreso->costo_total),
            'salida' => $ingreso->salida?->numero(),
            'usuario' => $ingreso->user?->name,
            'registrado' => $ingreso->created_at?->toIso8601String(),
            'anulado' => $ingreso->estaAnulado(),
            'anulado_at' => $ingreso->anulado_at?->toIso8601String(),
        ];
    }
}
