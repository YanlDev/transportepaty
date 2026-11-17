<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Repartir el saldo a favor de un cobro entre más facturas. Que no pase de
 * lo que cada factura debe ni del saldo del cobro lo revisa
 * `RegistroDeCobros`.
 */
class AplicarCobroRequest extends FormRequest
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
            'aplicaciones' => ['required', 'array', 'min:1', 'max:100'],
            'aplicaciones.*.factura_id' => ['required', 'integer', 'distinct', Rule::exists('facturas', 'id')->whereNull('anulada_at')],
            'aplicaciones.*.monto' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, float>
     */
    public function aplicaciones(): array
    {
        return collect((array) $this->input('aplicaciones', []))
            ->mapWithKeys(fn (array $aplicacion): array => [(int) $aplicacion['factura_id'] => round((float) $aplicacion['monto'], 2)])
            ->all();
    }
}
