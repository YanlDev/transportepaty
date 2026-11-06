<?php

namespace App\Http\Requests;

use App\Enums\ZonaRuta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPuntoAbastecimientoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:60', Rule::unique('puntos_abastecimiento', 'nombre')->ignore($this->route('punto'))],
            'zona' => ['nullable', Rule::enum(ZonaRuta::class)],
            'detalle' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ];
    }
}
