<?php

namespace App\Http\Requests;

use App\Enums\Moneda;
use App\Models\Viaje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Emitir una factura sobre uno o varios viajes. Solo el número es obligatorio:
 * el resto se completa en línea, celda por celda, a medida que se conoce.
 */
class StoreFacturaRequest extends FormRequest
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
            'numero' => ['required', 'string', 'max:30', Rule::unique('facturas', 'numero')],
            'fecha_emision' => ['nullable', 'date'],
            'monto' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'moneda' => ['nullable', Rule::enum(Moneda::class)],
            'viaje_ids' => ['required', 'array', 'min:1'],
            'viaje_ids.*' => ['integer', Rule::exists('viajes', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var list<int> $ids */
            $ids = $this->input('viaje_ids', []);

            if ($ids === []) {
                return;
            }

            // Un viaje en dos facturas se cobraría dos veces.
            $tomados = Viaje::query()
                ->whereIn('id', $ids)
                ->whereNotNull('factura_id')
                ->pluck('numero_gr');

            if ($tomados->isNotEmpty()) {
                $validator->errors()->add(
                    'viaje_ids',
                    'Ya están facturados: '.$tomados->join(', ').'.',
                );
            }
        });
    }
}
