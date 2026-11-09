<?php

namespace App\Http\Requests\Almacen;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo contado de un artículo. Si el artículo ya está en el conteo, se
 * reemplaza: se recuenta, no se suma.
 */
class LineaInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'articulo_id' => ['required', 'integer', Rule::exists('articulos', 'id')],
            'cantidad_contada' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'costo_estimado' => ['boolean'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'articulo_id.required' => 'Elige el artículo.',
            'cantidad_contada.required' => 'Anota cuánto hay, aunque sea 0.',
        ];
    }
}
