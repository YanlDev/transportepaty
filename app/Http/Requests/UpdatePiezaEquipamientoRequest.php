<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos de una pieza con código: qué es, su serie y cuándo vence.
 */
class UpdatePiezaEquipamientoRequest extends FormRequest
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
            'descripcion' => ['nullable', 'string', 'max:120'],
            'serie' => ['nullable', 'string', 'max:60'],
            'vence_el' => ['nullable', 'date'],
        ];
    }
}
