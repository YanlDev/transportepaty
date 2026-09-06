<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

abstract class ClienteRequest extends FormRequest
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
            // 11 dígitos: 20 para empresas, 10 para persona natural con
            // negocio. No se valida el dígito verificador —los RUC entran
            // desde la GR, no a mano, y rechazar uno real por eso sería peor.
            'ruc' => ['required', 'digits:11', $this->reglaRucUnico()],
            'razon_social' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:60'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'recurrente' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    abstract protected function reglaRucUnico(): Unique;
}
