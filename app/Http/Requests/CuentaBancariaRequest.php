<?php

namespace App\Http\Requests;

use App\Enums\Moneda;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

abstract class CuentaBancariaRequest extends FormRequest
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
            'banco' => ['required', 'string', 'max:60'],
            'alias' => ['required', 'string', 'max:60'],
            // El formato del número varía por banco (guiones, largos
            // distintos), así que se valida el largo y nada más: rechazar uno
            // real por no encajar en un patrón sería peor que aceptarlo.
            'numero_cuenta' => ['required', 'string', 'max:40', $this->reglaCuentaUnica()],
            'cci' => ['nullable', 'string', 'max:40'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'activa' => ['required', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    abstract protected function reglaCuentaUnica(): Unique;
}
