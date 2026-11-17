<?php

namespace App\Http\Requests;

use App\Models\Factura;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Anular una factura pide decir por qué: es lo que después explica el hueco
 * en la cobranza. Una factura con un cobro registrado no se anula de una.
 */
class AnularFacturaRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * El cobro quedaría sin factura que lo explique: primero se quita (y
     * queda en el historial), después se anula.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $factura = $this->route('factura');

                if (! $factura instanceof Factura) {
                    return;
                }

                if ($factura->estaAnulada()) {
                    $validator->errors()->add('motivo', "La factura {$factura->numero} ya está anulada.");
                } elseif ($factura->tieneCobro()) {
                    $validator->errors()->add('motivo', "La factura {$factura->numero} tiene un cobro registrado: quita la fecha de pago y la de la detracción antes de anularla.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué se anula.',
            'motivo.min' => 'Escribe por qué se anula (al menos 5 letras).',
        ];
    }
}
