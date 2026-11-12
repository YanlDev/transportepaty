<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El peso de una GR de bienes fiscalizados, en toneladas. El tope deja pasar
 * cualquier carga real de un tracto con carreta y frena el que escribe
 * kilos por toneladas (30000 en vez de 30).
 */
class UpdatePesoViajeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'peso' => ['required', 'numeric', 'gt:0', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'peso.required' => 'Escribe el peso en toneladas.',
            'peso.gt' => 'El peso tiene que ser mayor que 0.',
            'peso.max' => 'Va en toneladas: :max TN como máximo.',
        ];
    }
}
