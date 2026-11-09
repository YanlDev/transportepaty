<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\UnidadMedida;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\ArticuloRequest;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\DetalleIngreso;
use App\Models\DetalleInventario;
use App\Models\DetalleSalida;
use App\Models\MovimientoAlmacen;
use App\Models\Vehiculo;
use App\Services\ExportadorAlmacen;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El catálogo de artículos y la ficha de cada uno con su kardex.
 */
class ArticuloController extends Controller
{
    use CatalogosAlmacen;

    public function create(): Response
    {
        $this->authorize('create', Articulo::class);

        return Inertia::render('almacen/articulos/form', [
            'articulo' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(ArticuloRequest $request): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $datos = $request->datos();

        $articulo = Articulo::create([
            ...$datos,
            'codigo' => $datos['codigo'] ?? Articulo::siguienteCodigo(),
        ]);

        // Desde un conteo o un ingreso se crea sin salir de la pantalla.
        if ($request->boolean('volver')) {
            return back()->with('toast', ['type' => 'success', 'message' => "Artículo {$articulo->codigo} creado: ya se puede elegir."]);
        }

        return to_route('almacen.articulos.show', $articulo)
            ->with('toast', ['type' => 'success', 'message' => "Artículo {$articulo->codigo} creado."]);
    }

    public function show(Request $request, Articulo $articulo): Response
    {
        $this->authorize('view', $articulo);

        $veCostos = $this->veCostos($request->user());
        $articulo->load('categoria');

        $movimientos = MovimientoAlmacen::query()
            ->where('articulo_id', $articulo->id)
            ->with(['vehiculo:id,placa', 'proveedor:id,razon_social,nombre_comercial', 'origen'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (MovimientoAlmacen $movimiento): array => [
                'id' => $movimiento->id,
                'fecha' => $movimiento->fecha->toDateString(),
                'tipo' => $movimiento->tipo->value,
                'tipo_label' => $movimiento->tipo->label(),
                'documento' => $movimiento->documento,
                'enlace' => $this->enlaceDocumento($movimiento),
                'detalle' => $movimiento->vehiculo?->placa ?? $movimiento->proveedor?->nombre(),
                'entrada' => (float) $movimiento->entrada,
                'salida' => (float) $movimiento->salida,
                'costo_unitario' => $this->costo($veCostos, $movimiento->costo_unitario),
                'costo_total' => $this->costo($veCostos, $movimiento->costo_total),
                'saldo_cantidad' => (float) $movimiento->saldo_cantidad,
                'saldo_costo_unitario' => $this->costo($veCostos, $movimiento->saldo_costo_unitario),
                'saldo_valor' => $this->costo($veCostos, $movimiento->saldo_valor),
            ]);

        // Dónde se fue: lo que se llevó cada unidad en el último año.
        $consumo = MovimientoAlmacen::query()
            ->where('articulo_id', $articulo->id)
            ->where('tipo', 'salida')
            ->whereNotNull('vehiculo_id')
            ->whereDate('fecha', '>=', RelojOperativo::fechaDeHoy()->subYear()->toDateString())
            ->groupBy('vehiculo_id')
            ->select('vehiculo_id', DB::raw('sum(salida) as cantidad'), DB::raw('sum(costo_total) as costo'))
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get();
        $placas = Vehiculo::withTrashed()->whereIn('id', $consumo->pluck('vehiculo_id'))->pluck('placa', 'id');

        return Inertia::render('almacen/articulos/show', [
            'articulo' => [
                'id' => $articulo->id,
                'codigo' => $articulo->codigo,
                'nombre' => $articulo->nombre,
                'numero_parte' => $articulo->numero_parte,
                'marca' => $articulo->marca,
                'categoria' => $articulo->categoria?->nombre,
                'unidad' => $articulo->unidad->abreviatura(),
                'unidad_label' => $articulo->unidad->label(),
                'unidad_compra' => $articulo->unidad_compra?->abreviatura(),
                'factor_compra' => $articulo->factor(),
                'ubicacion' => $articulo->ubicacion,
                'marcas_compatibles' => $articulo->marcas_compatibles ?? [],
                'observacion' => $articulo->observacion,
                'activo' => $articulo->activo,
                'stock' => (float) $articulo->stock,
                'stock_minimo' => $articulo->stock_minimo === null ? null : (float) $articulo->stock_minimo,
                'bajo_minimo' => $articulo->estaBajoMinimo(),
                'costo_promedio' => $this->costo($veCostos, $articulo->costo_promedio),
                'valor' => $this->costo($veCostos, $articulo->valor),
            ],
            'movimientos' => $movimientos,
            'consumo' => $consumo->map(fn (MovimientoAlmacen $fila): array => [
                'placa' => $placas[$fila->vehiculo_id] ?? '—',
                'cantidad' => (float) $fila->getAttribute('cantidad'),
                'costo' => $this->costo($veCostos, $fila->getAttribute('costo')),
            ])->values()->all(),
        ]);
    }

    public function edit(Articulo $articulo): Response
    {
        $this->authorize('update', $articulo);

        return Inertia::render('almacen/articulos/form', [
            'articulo' => [
                'id' => $articulo->id,
                'codigo' => $articulo->codigo,
                'nombre' => $articulo->nombre,
                'numero_parte' => $articulo->numero_parte,
                'marca' => $articulo->marca,
                'categoria_articulo_id' => $articulo->categoria_articulo_id,
                'unidad' => $articulo->unidad->value,
                'unidad_compra' => $articulo->unidad_compra?->value,
                'factor_compra' => (float) $articulo->factor_compra,
                'stock_minimo' => $articulo->stock_minimo === null ? null : (float) $articulo->stock_minimo,
                'ubicacion' => $articulo->ubicacion,
                'marcas_compatibles' => $articulo->marcas_compatibles ?? [],
                'observacion' => $articulo->observacion,
                'activo' => $articulo->activo,
                'tiene_movimientos' => $articulo->movimientos()->exists(),
            ],
            ...$this->opciones(),
        ]);
    }

    public function update(ArticuloRequest $request, Articulo $articulo): RedirectResponse
    {
        $this->authorize('update', $articulo);

        $datos = $request->datos();

        // Con movimientos, la unidad ya no se cambia: el kardex está contado
        // en ella y cambiarla desordenaría todas las cantidades.
        if ($articulo->movimientos()->exists() && $datos['unidad'] !== $articulo->unidad->value) {
            return back()->withErrors(['unidad' => 'El artículo ya tiene movimientos en '.$articulo->unidad->label().': su unidad no se cambia.']);
        }

        $articulo->update([...$datos, 'codigo' => $datos['codigo'] ?? $articulo->codigo]);

        return to_route('almacen.articulos.show', $articulo)
            ->with('toast', ['type' => 'success', 'message' => 'Artículo actualizado.']);
    }

    /**
     * Una categoría nueva, desde el formulario del artículo.
     */
    public function crearCategoria(Request $request): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias_articulo', 'nombre')],
        ], ['nombre.unique' => 'Esa categoría ya existe.']);

        CategoriaArticulo::create(['nombre' => mb_convert_case(trim($datos['nombre']), MB_CASE_TITLE)]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Categoría creada.']);
    }

    /**
     * El kardex del artículo en Excel, en el orden del formato de SUNAT
     * (registro de inventario permanente valorizado).
     */
    public function exportarKardex(Request $request, Articulo $articulo, ExportadorAlmacen $exportador): BinaryFileResponse
    {
        $this->authorize('view', $articulo);
        $this->authorize('verCostos', Articulo::class);

        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $desde = $datos['desde'] ?? RelojOperativo::fechaDeHoy()->startOfYear()->toDateString();
        $hasta = $datos['hasta'] ?? RelojOperativo::hoy();

        $ruta = tempnam(sys_get_temp_dir(), 'kardex');
        $exportador->kardex([$articulo], $desde, $hasta, $ruta);

        return response()
            ->download($ruta, "kardex-{$articulo->codigo}-{$desde}-{$hasta}.xlsx")
            ->deleteFileAfterSend();
    }

    /**
     * El kardex de todos los artículos con movimientos en el período, uno
     * tras otro, como lo pide el libro de inventarios.
     */
    public function exportarKardexGeneral(Request $request, ExportadorAlmacen $exportador): BinaryFileResponse
    {
        $this->authorize('viewAny', Articulo::class);
        $this->authorize('verCostos', Articulo::class);

        $datos = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $articulos = Articulo::query()
            ->whereHas('movimientos', fn ($query) => $query->whereDate('fecha', '<=', $datos['hasta']))
            ->orderBy('codigo')
            ->get();

        $ruta = tempnam(sys_get_temp_dir(), 'kardex');
        $exportador->kardex($articulos->all(), $datos['desde'], $datos['hasta'], $ruta);

        return response()
            ->download($ruta, "kardex-almacen-{$datos['desde']}-{$datos['hasta']}.xlsx")
            ->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'categorias' => CategoriaArticulo::query()->orderBy('nombre')->get(['id', 'nombre']),
            'unidades' => array_map(
                fn (UnidadMedida $unidad): array => ['value' => $unidad->value, 'label' => "{$unidad->label()} ({$unidad->abreviatura()})"],
                UnidadMedida::cases(),
            ),
            'marcasFlota' => Vehiculo::query()
                ->whereNotNull('marca')
                ->distinct()
                ->orderBy('marca')
                ->pluck('marca')
                ->map(fn (string $marca): string => mb_strtoupper(trim($marca)))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * A qué documento lleva la fila del kardex.
     *
     * @return array{tipo: string, id: int}|null
     */
    private function enlaceDocumento(MovimientoAlmacen $movimiento): ?array
    {
        $origen = $movimiento->origen;

        return match (true) {
            $origen instanceof DetalleIngreso => ['tipo' => 'ingreso', 'id' => $origen->ingreso_almacen_id],
            $origen instanceof DetalleSalida => ['tipo' => 'salida', 'id' => $origen->salida_almacen_id],
            $origen instanceof DetalleInventario => ['tipo' => 'inventario', 'id' => $origen->inventario_almacen_id],
            default => null,
        };
    }
}
