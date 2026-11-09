<?php

namespace App\Http\Requests\Almacen;

use App\Enums\UnidadMedida;
use App\Models\Articulo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un artículo del catálogo. El código es opcional: si no
 * se escribe, se le pone el correlativo.
 */
class ArticuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => $this->filled('codigo') ? mb_strtoupper(trim((string) $this->input('codigo'))) : null,
            'nombre' => mb_strtoupper(trim((string) $this->input('nombre'))),
            'unidad_compra' => $this->filled('unidad_compra') ? $this->input('unidad_compra') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $articulo = $this->route('articulo');

        return [
            'codigo' => ['nullable', 'string', 'max:30', Rule::unique('articulos', 'codigo')->ignore($articulo instanceof Articulo ? $articulo->id : null)],
            'nombre' => ['required', 'string', 'max:255'],
            'numero_parte' => ['nullable', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:255'],
            'categoria_articulo_id' => ['nullable', 'integer', Rule::exists('categorias_articulo', 'id')],
            'unidad' => ['required', Rule::enum(UnidadMedida::class)],
            'unidad_compra' => ['nullable', Rule::enum(UnidadMedida::class), 'different:unidad'],
            'factor_compra' => ['nullable', 'required_with:unidad_compra', 'numeric', 'gt:0', 'max:100000'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'marcas_compatibles' => ['nullable', 'array'],
            'marcas_compatibles.*' => ['string', 'max:30'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ese código ya lo tiene otro artículo.',
            'unidad_compra.different' => 'Si se compra igual que como se despacha, deja la unidad de compra vacía.',
            'factor_compra.required_with' => 'Di cuántas unidades del almacén trae cada unidad de compra.',
        ];
    }

    /**
     * Los datos listos para guardar.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->validated();

        $datos['factor_compra'] = $datos['unidad_compra'] === null ? 1 : $datos['factor_compra'];
        $datos['marcas_compatibles'] = array_values(array_unique(array_map(
            fn (string $marca): string => mb_strtoupper(trim($marca)),
            $datos['marcas_compatibles'] ?? [],
        ))) ?: null;

        return $datos;
    }
}
