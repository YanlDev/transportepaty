<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\DetalleIngreso;
use App\Models\IngresoAlmacen;
use App\Models\SalidaAlmacen;
use App\Models\UbicacionAlmacen;
use App\Services\ExportadorAlmacen;
use App\Services\RelojOperativo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La portada del almacén: el stock de cada artículo, cuánto vale y qué está
 * por debajo del mínimo, con los accesos a ingresos, vales y conteos.
 */
class AlmacenController extends Controller
{
    use CatalogosAlmacen;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Articulo::class);

        $veCostos = $this->veCostos($request->user());
        $filtros = $this->filtros($request);

        $articulos = $this->consulta($filtros)
            ->with(['categoria', 'ubicacion'])
            ->orderBy('nombre')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Articulo $articulo): array => [
                'id' => $articulo->id,
                'codigo' => $articulo->codigo,
                'nombre' => $articulo->nombre,
                'numero_parte' => $articulo->numero_parte,
                'marca' => $articulo->marca,
                'categoria' => $articulo->categoria?->nombre,
                'unidad' => $articulo->unidad->abreviatura(),
                'ubicacion' => $articulo->ubicacion?->nombre,
                'stock' => (float) $articulo->stock,
                'stock_minimo' => $articulo->stock_minimo === null ? null : (float) $articulo->stock_minimo,
                'bajo_minimo' => $articulo->estaBajoMinimo(),
                'activo' => $articulo->activo,
                'costo_promedio' => $this->costo($veCostos, $articulo->costo_promedio),
                'valor' => $this->costo($veCostos, $articulo->valor),
            ]);

        $activos = Articulo::query()->where('activo', true);

        return Inertia::render('almacen/index', [
            'articulos' => $articulos,
            'filtros' => $filtros,
            'categorias' => CategoriaArticulo::query()->orderBy('nombre')->get(['id', 'nombre']),
            'ubicaciones' => UbicacionAlmacen::query()->orderBy('nombre')->get(['id', 'nombre']),
            // Lo que entró con fecha de vencimiento y vence en los próximos
            // 30 días (o ya venció), de artículos que todavía tienen stock. No
            // hay lotes: es un aviso para ir a mirar, no un saldo por fecha.
            'porVencer' => DetalleIngreso::query()
                ->whereNotNull('vence_el')
                ->whereDate('vence_el', '<=', RelojOperativo::fechaDeHoy()->addDays(30)->toDateString())
                ->whereIn('ingreso_almacen_id', IngresoAlmacen::query()->vigentes()->select('id'))
                ->whereIn('articulo_id', Articulo::query()->where('stock', '>', 0)->select('id'))
                ->with(['articulo:id,codigo,nombre', 'ingreso:id'])
                ->orderBy('vence_el')
                ->limit(20)
                ->get()
                ->map(fn (DetalleIngreso $detalle): array => [
                    'id' => $detalle->id,
                    'articulo_id' => $detalle->articulo_id,
                    'nombre' => $detalle->articulo->nombre,
                    'ingreso' => $detalle->ingreso->numero(),
                    'vence_el' => (string) $detalle->vence_el?->toDateString(),
                    'vencido' => $detalle->vence_el?->lt(RelojOperativo::fechaDeHoy()) ?? false,
                ])
                ->values()
                ->all(),
            'resumen' => [
                'articulos' => (clone $activos)->count(),
                'bajo_minimo' => (clone $activos)->bajoMinimo()->count(),
                'sin_stock' => (clone $activos)->where('stock', '<=', 0)->count(),
                // Todo lo que hay, aunque el artículo esté dado de baja: mientras
                // tenga stock, vale.
                'valor' => $this->costo($veCostos, Articulo::query()->sum('valor')),
                'baja_con_stock' => Articulo::query()->where('activo', false)->where('stock', '>', 0)->count(),
                'ingresos_mes' => IngresoAlmacen::query()->vigentes()->whereDate('fecha', '>=', RelojOperativo::inicioDelMes()->toDateString())->count(),
                'salidas_mes' => SalidaAlmacen::query()->vigentes()->whereDate('fecha', '>=', RelojOperativo::inicioDelMes()->toDateString())->count(),
            ],
        ]);
    }

    /**
     * El stock valorizado en Excel, con los mismos filtros de la pantalla.
     */
    public function exportar(Request $request, ExportadorAlmacen $exportador): BinaryFileResponse
    {
        $this->authorize('viewAny', Articulo::class);

        $ruta = tempnam(sys_get_temp_dir(), 'stock');

        $exportador->stock(
            $this->consulta($this->filtros($request))->with(['categoria', 'ubicacion'])->orderBy('nombre')->get(),
            $this->veCostos($request->user()),
            $ruta,
        );

        return response()
            ->download($ruta, 'stock-almacen-'.now()->format('Y-m-d').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return array{buscar: string, categoria: string, ubicacion: string, estado: string}
     */
    private function filtros(Request $request): array
    {
        return [
            'buscar' => $request->string('buscar')->trim()->value(),
            'categoria' => $request->string('categoria')->value(),
            'ubicacion' => $request->string('ubicacion')->value(),
            'estado' => $request->string('estado')->value(),
        ];
    }

    /**
     * @param  array{buscar: string, categoria: string, ubicacion: string, estado: string}  $filtros
     * @return Builder<Articulo>
     */
    private function consulta(array $filtros): Builder
    {
        return Articulo::query()
            ->where('activo', $filtros['estado'] !== 'inactivos')
            ->when($filtros['buscar'] !== '', fn (Builder $query) => $query->buscar($filtros['buscar']))
            ->when($filtros['categoria'] !== '', fn (Builder $query) => $query->where('categoria_articulo_id', (int) $filtros['categoria']))
            ->when($filtros['ubicacion'] !== '', fn (Builder $query) => $query->where('ubicacion_almacen_id', (int) $filtros['ubicacion']))
            ->when($filtros['estado'] === 'bajo_minimo', fn (Builder $query) => $query->bajoMinimo())
            ->when($filtros['estado'] === 'sin_stock', fn (Builder $query) => $query->where('stock', '<=', 0))
            ->when($filtros['estado'] === 'con_stock', fn (Builder $query) => $query->where('stock', '>', 0));
    }
}
