<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los cortes del semáforo de llantas: el de «cambiar» va por debajo del de
 * «revisar», si no el ámbar no existiría.
 */
class UpdateParametrosLlantasRequest extends FormRequest
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
            'llantas_revisar_mm' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'llantas_cambiar_mm' => ['required', 'numeric', 'min:0', 'max:30', 'lt:llantas_revisar_mm'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'llantas_cambiar_mm.lt' => 'Tiene que ser menor que el de «revisar».',
        ];
    }
}
