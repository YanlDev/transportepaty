<?php

namespace App\Http\Requests;

use App\Enums\UnidadMedida;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El mecánico pide un repuesto: lo elige del catálogo del almacén o, si no
 * está ahí, lo escribe.
 */
class PedirRepuestoRequest extends FormRequest
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
            'articulo_id' => ['nullable', 'integer', Rule::exists('articulos', 'id')->where('activo', true)],
            'descripcion' => ['nullable', 'required_without:articulo_id', 'string', 'min:3', 'max:300'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'unidad' => ['nullable', Rule::enum(UnidadMedida::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required_without' => 'Elige el repuesto del almacén o escribe cuál es.',
            'descripcion.min' => 'Describe un poco más el repuesto.',
            'cantidad.required' => 'Di cuántos necesitas.',
            'cantidad.gt' => 'La cantidad tiene que ser mayor que cero.',
        ];
    }
}
