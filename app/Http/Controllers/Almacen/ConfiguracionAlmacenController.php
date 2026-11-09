<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\UbicacionAlmacen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que ordena el almacén: las categorías de los artículos, los estantes
 * donde se guardan y los mínimos de stock, estos últimos en bloque para no
 * entrar ficha por ficha.
 *
 * Una categoría o un estante con artículos no se borra a secas: sus
 * artículos pasan a otro (se fusionan) o se quedan sin él.
 */
class ConfiguracionAlmacenController extends Controller
{
    public function index(): Response
    {
        $this->authorize('create', Articulo::class);

        return Inertia::render('almacen/configuracion', [
            'categorias' => CategoriaArticulo::query()
                ->withCount('articulos')
                ->orderBy('nombre')
                ->get()
                ->map(fn (CategoriaArticulo $categoria): array => [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'articulos' => (int) $categoria->getAttribute('articulos_count'),
                ])
                ->values()
                ->all(),
            'ubicaciones' => UbicacionAlmacen::query()
                ->withCount('articulos')
                ->orderBy('nombre')
                ->get()
                ->map(fn (UbicacionAlmacen $ubicacion): array => [
                    'id' => $ubicacion->id,
                    'nombre' => $ubicacion->nombre,
                    'descripcion' => $ubicacion->descripcion,
                    'articulos' => (int) $ubicacion->getAttribute('articulos_count'),
                ])
                ->values()
                ->all(),
            'articulos' => Articulo::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (Articulo $articulo): array => [
                    'id' => $articulo->id,
                    'codigo' => $articulo->codigo,
                    'nombre' => $articulo->nombre,
                    'categoria_id' => $articulo->categoria_articulo_id,
                    'ubicacion_id' => $articulo->ubicacion_almacen_id,
                    'unidad' => $articulo->unidad->abreviatura(),
                    'stock' => (float) $articulo->stock,
                    'stock_minimo' => $articulo->stock_minimo === null ? null : (float) $articulo->stock_minimo,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function crearCategoria(Request $request): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $nombre = $this->nombreCategoria($request);

        CategoriaArticulo::create(['nombre' => $nombre]);

        return back()->with('toast', ['type' => 'success', 'message' => "Categoría «{$nombre}» creada."]);
    }

    public function renombrarCategoria(Request $request, CategoriaArticulo $categoria): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $categoria->update(['nombre' => $this->nombreCategoria($request, $categoria)]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Categoría renombrada.']);
    }

    /**
     * Quita la categoría. Si tiene artículos, pasan a la que se elija (se
     * fusionan); sin destino quedan sin categoría.
     */
    public function quitarCategoria(Request $request, CategoriaArticulo $categoria): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $destino = $request->validate([
            'mover_a' => ['nullable', 'integer', Rule::exists('categorias_articulo', 'id'), Rule::notIn([$categoria->id])],
        ])['mover_a'] ?? null;

        $movidos = DB::transaction(function () use ($categoria, $destino): int {
            $movidos = Articulo::query()
                ->where('categoria_articulo_id', $categoria->id)
                ->update(['categoria_articulo_id' => $destino]);

            $categoria->delete();

            return $movidos;
        });

        return back()->with('toast', ['type' => 'success', 'message' => $this->mensajeQuitado("Categoría «{$categoria->nombre}»", $movidos, $destino !== null)]);
    }

    public function crearUbicacion(Request $request): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $ubicacion = UbicacionAlmacen::create($this->datosUbicacion($request));

        return back()->with('toast', ['type' => 'success', 'message' => "Ubicación «{$ubicacion->nombre}» creada."]);
    }

    public function editarUbicacion(Request $request, UbicacionAlmacen $ubicacion): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $ubicacion->update($this->datosUbicacion($request, $ubicacion));

        return back()->with('toast', ['type' => 'success', 'message' => 'Ubicación actualizada.']);
    }

    /**
     * Quita el estante. Lo que estaba ahí pasa al que se elija o queda sin
     * ubicación.
     */
    public function quitarUbicacion(Request $request, UbicacionAlmacen $ubicacion): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $destino = $request->validate([
            'mover_a' => ['nullable', 'integer', Rule::exists('ubicaciones_almacen', 'id'), Rule::notIn([$ubicacion->id])],
        ])['mover_a'] ?? null;

        $movidos = DB::transaction(function () use ($ubicacion, $destino): int {
            $movidos = Articulo::query()
                ->where('ubicacion_almacen_id', $ubicacion->id)
                ->update(['ubicacion_almacen_id' => $destino]);

            $ubicacion->delete();

            return $movidos;
        });

        return back()->with('toast', ['type' => 'success', 'message' => $this->mensajeQuitado("Ubicación «{$ubicacion->nombre}»", $movidos, $destino !== null)]);
    }

    /**
     * Los mínimos de varios artículos de una vez. Vacío quita el mínimo.
     */
    public function guardarMinimos(Request $request): RedirectResponse
    {
        $this->authorize('create', Articulo::class);

        $datos = $request->validate([
            'minimos' => ['required', 'array', 'min:1'],
            'minimos.*.id' => ['required', 'integer', 'distinct', Rule::exists('articulos', 'id')],
            'minimos.*.stock_minimo' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        DB::transaction(function () use ($datos): void {
            foreach ($datos['minimos'] as $fila) {
                Articulo::query()->findOrFail((int) $fila['id'])->update(['stock_minimo' => $fila['stock_minimo'] ?? null]);
            }
        });

        $cuantos = count($datos['minimos']);

        return back()->with('toast', ['type' => 'success', 'message' => $cuantos === 1 ? 'Mínimo guardado.' : "{$cuantos} mínimos guardados."]);
    }

    private function nombreCategoria(Request $request, ?CategoriaArticulo $categoria = null): string
    {
        $nombre = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias_articulo', 'nombre')->ignore($categoria?->id)],
        ], ['nombre.unique' => 'Esa categoría ya existe.'])['nombre'];

        return mb_convert_case(trim($nombre), MB_CASE_TITLE);
    }

    /**
     * @return array{nombre: string, descripcion: string|null}
     */
    private function datosUbicacion(Request $request, ?UbicacionAlmacen $ubicacion = null): array
    {
        $request->merge(['nombre' => mb_strtoupper(trim((string) $request->input('nombre')))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('ubicaciones_almacen', 'nombre')->ignore($ubicacion?->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], ['nombre.unique' => 'Esa ubicación ya existe.']);

        return ['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null];
    }

    private function mensajeQuitado(string $que, int $movidos, bool $fusionada): string
    {
        if ($movidos === 0) {
            return "{$que} quitada.";
        }

        $articulos = $movidos === 1 ? 'su único artículo pasó' : "sus {$movidos} artículos pasaron";

        return $fusionada
            ? "{$que} quitada: {$articulos} a la elegida."
            : "{$que} quitada: {$articulos} a quedar sin asignar.";
    }
}
