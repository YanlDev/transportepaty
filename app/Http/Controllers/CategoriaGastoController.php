<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Las categorías de los vales de gasto, que lleva gerencia. No se borran:
 * una que ya no se usa se desactiva y deja de ofrecerse al pedir, pero los
 * vales viejos la siguen mostrando.
 */
class CategoriaGastoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('administrar', CategoriaGasto::class);

        CategoriaGasto::create($this->validar($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Categoría agregada.']);
    }

    public function update(Request $request, CategoriaGasto $categoria): RedirectResponse
    {
        $this->authorize('administrar', CategoriaGasto::class);

        $categoria->update($this->validar($request, $categoria));

        return back()->with('toast', ['type' => 'success', 'message' => 'Categoría actualizada.']);
    }

    /**
     * @return array{nombre: string, activa?: bool}
     */
    private function validar(Request $request, ?CategoriaGasto $categoria = null): array
    {
        /** @var array{nombre: string, activa?: bool} */
        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:80', Rule::unique('categorias_gasto', 'nombre')->ignore($categoria?->id)],
            'activa' => ['sometimes', 'boolean'],
        ], [
            'nombre.required' => 'Ponle nombre a la categoría.',
            'nombre.unique' => 'Ya hay una categoría con ese nombre.',
        ]);
    }
}
