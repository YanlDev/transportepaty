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
 *
 * Un viaje que ya tiene factura puede recibir otra —el flete y la estadía, o
 * un cobro partido—, así que no se rechaza; el doble cobro accidental lo
 * evita el número único y la pantalla, que avisa qué GR ya están facturadas.
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
            // Una GR anulada no es un viaje y no se cobra: la factura quedaría
            // escondida, porque la cobranza no muestra las anuladas.
            'viaje_ids.*' => ['integer', 'distinct', Rule::exists('viajes', 'id')->whereNull('anulada_at')],
        ];
    }

    /**
     * Una GR marcada «no se factura» no entra en una factura: si de verdad hay
     * que cobrarla, primero se le quita la marca.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $noFacturables = Viaje::query()
                    ->whereIn('id', (array) $this->input('viaje_ids', []))
                    ->whereNotNull('no_facturable_at')
                    ->pluck('numero_gr');

                if ($noFacturables->isNotEmpty()) {
                    $validator->errors()->add(
                        'viaje_ids',
                        'Están marcadas como «no se factura»: '.$noFacturables->join(', ').'.',
                    );
                }
            },
        ];
    }
}
