<?php

namespace App\Http\Requests;

use App\Enums\CondicionLlanta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corregir los datos de una llanta: lo que se escribió mal al instalarla.
 * La fecha y el km de instalación se corrigen en su movimiento, para que
 * llanta e historial no digan cosas distintas.
 */
class UpdateLlantaRequest extends FormRequest
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
            'marca' => ['nullable', 'string', 'max:255'],
            'medida' => ['nullable', 'string', 'max:50'],
            'codigo' => ['nullable', 'string', 'max:100'],
            'condicion' => ['required', Rule::enum(CondicionLlanta::class)],
        ];
    }
}
