<?php

namespace App\Http\Requests;

use App\Enums\AreaGasto;
use App\Services\Archivos\ReglasDeArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedir un vale de gasto: qué se paga, cuánto, a quién y para qué. El área
 * sale del rol de quien pide; solo viaja si la corrige.
 */
class ValeGastoRequest extends FormRequest
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
            'area' => ['nullable', Rule::enum(AreaGasto::class)],
            'categoria_gasto_id' => ['required', 'integer', Rule::exists('categorias_gasto', 'id')->where('activa', true)],
            'concepto' => ['required', 'string', 'min:3', 'max:255'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'beneficiario' => ['required', 'string', 'min:3', 'max:255'],
            'vehiculo_id' => ['nullable', 'integer', Rule::exists('vehiculos', 'id')],
            'sustento' => ['nullable', 'array', 'max:3'],
            'sustento.*' => ReglasDeArchivo::imagenOPdf(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_gasto_id.required' => 'Elige en qué categoría cae el gasto.',
            'concepto.required' => 'Di qué se va a pagar.',
            'motivo.required' => 'Di para qué es el gasto.',
            'motivo.min' => 'Explica un poco más para qué es el gasto.',
            'monto.required' => 'Pon cuánto se va a gastar.',
            'monto.gt' => 'El monto tiene que ser mayor a cero.',
            'beneficiario.required' => 'Di a quién se le va a pagar.',
            'sustento.max' => 'Hasta :max archivos de sustento por vale.',
            'sustento.*.mimetypes' => 'El sustento tiene que ser un PDF o una foto (JPG, PNG, WEBP o HEIC).',
        ];
    }
}
