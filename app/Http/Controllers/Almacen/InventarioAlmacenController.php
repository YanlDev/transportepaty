<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\EstadoInventario;
use App\Enums\UnidadMedida;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\Almacen\InventarioRequest;
use App\Http\Requests\Almacen\LineaInventarioRequest;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\DetalleInventario;
use App\Models\InventarioAlmacen;
use App\Models\UbicacionAlmacen;
use App\Services\Kardex;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los conteos físicos. Así arranca el almacén —no hay un Excel de partida:
 * se cuenta estante por estante y el primer conteo es el saldo inicial— y
 * así se controla después: lo que no cuadra entra o sale como ajuste.
 */
class InventarioAlmacenController extends Controller
{
    use CatalogosAlmacen;

    public function __construct(private readonly Kardex $kardex) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventarioAlmacen::class);

        $inventarios = InventarioAlmacen::query()
            ->with(['user:id,name', 'cerradoPor:id,name'])
            ->withCount('detalles')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (InventarioAlmacen $inventario): array => [
                'id' => $inventario->id,
                'numero' => $inventario->numero(),
                'fecha' => $inventario->fecha->toDateString(),
                'estado' => $inventario->estaAnulado() ? 'anulado' : $inventario->estado->value,
                'estado_label' => $inventario->estaAnulado() ? 'Anulado' : $inventario->estado->label(),
                'observacion' => $inventario->observacion,
                'lineas' => $inventario->detalles_count,
                'usuario' => $inventario->user?->name,
                'cerrado_por' => $inventario->cerradoPor?->name,
                'cerrado_at' => $inventario->cerrado_at?->toIso8601String(),
            ]);

        return Inertia::render('almacen/inventarios/index', [
            'inventarios' => $inventarios,
            'hayArticulos' => Articulo::query()->exists(),
            'hoy' => RelojOperativo::hoy(),
        ]);
    }

    public function store(InventarioRequest $request): RedirectResponse
    {
        $this->authorize('create', InventarioAlmacen::class);

        $inventario = InventarioAlmacen::create([
            ...$request->validated(),
            'estado' => EstadoInventario::Abierto,
            'user_id' => $request->user()?->id,
        ]);

        return to_route('almacen.inventarios.show', $inventario)
            ->with('toast', ['type' => 'success', 'message' => "Conteo {$inventario->numero()} abierto: anota lo que hay en cada estante."]);
    }

    public function show(Request $request, InventarioAlmacen $inventario): Response
    {
        $this->authorize('view', $inventario);

        $veCostos = $this->veCostos($request->user());
        $inventario->load(['user:id,name', 'cerradoPor:id,name', 'anuladoPor:id,name', 'detalles.articulo.ubicacion']);

        return Inertia::render('almacen/inventarios/show', [
            'inventario' => [
                'id' => $inventario->id,
                'numero' => $inventario->numero(),
                'fecha' => $inventario->fecha->toDateString(),
                'estado' => $inventario->estaAnulado() ? 'anulado' : $inventario->estado->value,
                'estado_label' => $inventario->estaAnulado() ? 'Anulado' : $inventario->estado->label(),
                'observacion' => $inventario->observacion,
                'usuario' => $inventario->user?->name,
                'cerrado_por' => $inventario->cerradoPor?->name,
                'cerrado_at' => $inventario->cerrado_at?->toIso8601String(),
                'anulado_por' => $inventario->anuladoPor?->name,
                'motivo_anulacion' => $inventario->motivo_anulacion,
                'detalles' => $inventario->detalles
                    ->sortByDesc('id')
                    ->map(fn (DetalleInventario $detalle): array => [
                        'id' => $detalle->id,
                        'articulo_id' => $detalle->articulo_id,
                        'codigo' => $detalle->articulo->codigo,
                        'nombre' => $detalle->articulo->nombre,
                        'ubicacion' => $detalle->articulo->ubicacion?->nombre,
                        'unidad' => $detalle->articulo->unidad->abreviatura(),
                        'cantidad_contada' => (float) $detalle->cantidad_contada,
                        // Abierto, lo que dice el sistema hoy; cerrado, lo que decía al cerrar.
                        'stock_sistema' => $detalle->stock_sistema !== null ? (float) $detalle->stock_sistema : (float) $detalle->articulo->stock,
                        'diferencia' => $detalle->diferencia !== null
                            ? (float) $detalle->diferencia
                            : round((float) $detalle->cantidad_contada - (float) $detalle->articulo->stock, 3),
                        'costo_unitario' => $this->costo($veCostos, $detalle->costo_unitario),
                        'costo_estimado' => $detalle->costo_estimado,
                        'costo_promedio' => $this->costo($veCostos, $detalle->articulo->costo_promedio),
                        'observacion' => $detalle->observacion,
                    ])
                    ->values()
                    ->all(),
            ],
            'articulos' => $this->articulosParaElegir($veCostos),
            'puedeEditar' => $request->user()?->can('update', $inventario) ?? false,
            'puedeAnular' => $request->user()?->can('anular', $inventario) ?? false,
            'puedeCrearArticulo' => $request->user()?->can('create', Articulo::class) ?? false,
            'categorias' => CategoriaArticulo::query()->orderBy('nombre')->get(['id', 'nombre']),
            'ubicaciones' => UbicacionAlmacen::query()->orderBy('nombre')->get(['id', 'nombre']),
            'unidades' => array_map(
                fn (UnidadMedida $unidad): array => ['value' => $unidad->value, 'label' => "{$unidad->label()} ({$unidad->abreviatura()})"],
                UnidadMedida::cases(),
            ),
        ]);
    }

    /**
     * Anota (o recuenta) un artículo en el conteo abierto.
     */
    public function guardarLinea(LineaInventarioRequest $request, InventarioAlmacen $inventario): RedirectResponse
    {
        $this->authorize('update', $inventario);

        $datos = $request->validated();

        $inventario->detalles()->updateOrCreate(
            ['articulo_id' => $datos['articulo_id']],
            [
                'cantidad_contada' => $datos['cantidad_contada'],
                'costo_unitario' => $datos['costo_unitario'] ?? null,
                'costo_estimado' => $request->boolean('costo_estimado'),
                'observacion' => $datos['observacion'] ?? null,
            ],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Anotado.']);
    }

    public function quitarLinea(InventarioAlmacen $inventario, DetalleInventario $detalle): RedirectResponse
    {
        $this->authorize('update', $inventario);
        abort_unless($detalle->inventario_almacen_id === $inventario->id, 404);

        $detalle->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Línea quitada del conteo.']);
    }

    /**
     * Cierra el conteo: las diferencias pasan al kardex.
     */
    public function cerrar(Request $request, InventarioAlmacen $inventario): RedirectResponse
    {
        $this->authorize('update', $inventario);

        if ($inventario->detalles()->doesntExist()) {
            throw ValidationException::withMessages(['inventario' => 'El conteo está vacío: anota al menos un artículo.']);
        }

        DB::transaction(function () use ($request, $inventario): void {
            $inventario->update([
                'estado' => EstadoInventario::Cerrado,
                'cerrado_at' => now(),
                'cerrado_por' => $request->user()?->id,
            ]);

            $this->kardex->registrarInventario($inventario, $request->user()?->id);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "Conteo {$inventario->numero()} cerrado: el stock quedó como se contó."]);
    }

    /**
     * Abierto, se descarta. Cerrado, sus ajustes salen del kardex.
     */
    public function anular(AnularDocumentoRequest $request, InventarioAlmacen $inventario): RedirectResponse
    {
        $this->authorize('anular', $inventario);

        $estabaCerrado = $inventario->estado === EstadoInventario::Cerrado;

        DB::transaction(function () use ($request, $inventario, $estabaCerrado): void {
            $inventario->update([
                'anulado_at' => now(),
                'anulado_por' => $request->user()?->id,
                'motivo_anulacion' => $request->validated('motivo'),
            ]);

            if ($estabaCerrado) {
                $this->kardex->anular($inventario);
            }
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => $estabaCerrado
                ? "Conteo {$inventario->numero()} anulado: sus ajustes salieron del kardex."
                : "Conteo {$inventario->numero()} descartado.",
        ]);
    }
}
