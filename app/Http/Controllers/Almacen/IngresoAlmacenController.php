<?php

namespace App\Http\Controllers\Almacen;

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
use App\Models\IngresoAlmacen;
use App\Models\Proveedor;
use App\Models\UbicacionAlmacen;
use App\Services\CostoDeCompra;
use App\Services\Kardex;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'tasaIgv' => CostoDeCompra::TASA_IGV,
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
        ], $datos['detalles']));

        $calculo = $this->costoDeCompra->calcular(
            $comprobante,
            $moneda,
            isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null,
            $request->boolean('precios_con_igv'),
            $lineas,
        );

        $ingreso = DB::transaction(function () use ($request, $datos, $comprobante, $moneda, $lineas, $calculo): IngresoAlmacen {
            $ingreso = IngresoAlmacen::create([
                'tipo' => TipoIngreso::Compra,
                'fecha' => $datos['fecha'],
                'proveedor_id' => $datos['proveedor_id'],
                'tipo_comprobante' => $comprobante,
                'serie_numero' => $comprobante === TipoComprobante::SinComprobante ? null : $datos['serie_numero'],
                'moneda' => $moneda,
                'tipo_cambio' => $moneda === Moneda::Dolares ? $datos['tipo_cambio'] : null,
                'precios_con_igv' => $request->boolean('precios_con_igv'),
                'subtotal' => $calculo['subtotal'],
                'igv' => $calculo['igv'],
                'total' => $calculo['total'],
                'costo_total' => $calculo['costo_total'],
                'observacion' => $datos['observacion'] ?? null,
                'user_id' => $request->user()?->id,
            ]);

            foreach ($datos['detalles'] as $i => $linea) {
                $ingreso->detalles()->create([
                    'articulo_id' => $linea['articulo_id'],
                    'cantidad' => $lineas[$i]['cantidad'],
                    'precio_unitario' => $lineas[$i]['precio_unitario'],
                    'factor' => $lineas[$i]['factor'],
                    ...$calculo['lineas'][$i],
                ]);
            }

            if ($request->hasFile('comprobante')) {
                $ingreso->addMediaFromRequest('comprobante')->toMediaCollection('comprobante');
            }

            $this->kardex->registrarIngreso($ingreso, $request->user()?->id);

            return $ingreso;
        });

        return to_route('almacen.ingresos.show', $ingreso)
            ->with('toast', ['type' => 'success', 'message' => "Ingreso {$ingreso->numero()} registrado: el stock ya se actualizó."]);
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
                'subtotal' => $this->costo($veCostos, $ingreso->subtotal),
                'igv' => $this->costo($veCostos, $ingreso->igv),
                'observacion' => $ingreso->observacion,
                'anulado_por' => $ingreso->anuladoPor?->name,
                'motivo_anulacion' => $ingreso->motivo_anulacion,
                'comprobante' => $archivo === null ? null : [
                    'nombre' => $archivo->file_name,
                    'url' => route('almacen.ingresos.comprobante', $ingreso),
                    'es_pdf' => $archivo->mime_type === 'application/pdf',
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
                    'cantidad' => (float) $detalle->cantidad,
                    'cantidad_base' => (float) $detalle->cantidad_base,
                    'precio_unitario' => $this->costo($veCostos, $detalle->precio_unitario),
                    'costo_unitario' => $this->costo($veCostos, $detalle->costo_unitario),
                    'costo_total' => $this->costo($veCostos, $detalle->costo_total),
                ])->values()->all(),
            ],
            'puedeAnular' => $request->user()?->can('anular', $ingreso) ?? false,
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
