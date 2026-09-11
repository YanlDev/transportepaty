<?php

namespace App\Http\Requests;

use App\Enums\Moneda;
use App\Models\Factura;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guardar una celda de la cobranza. Cada campo es `sometimes` porque la
 * edición en línea manda solo el que se acaba de tocar: exigir el resto
 * obligaría a reenviar toda la fila en cada tecla.
 */
class PatchFacturaRequest extends FormRequest
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
            'numero' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('facturas', 'numero')->ignore($this->factura())],
            // Corregible pero no borrable: la columna no admite null y una
            // factura sin fecha de emisión no existe.
            'fecha_emision' => ['sometimes', 'required', 'date'],
            'monto' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'moneda' => ['sometimes', 'required', Rule::enum(Moneda::class)],

            // La fecha de pago no exige la cuenta acá —se cargan en celdas
            // distintas y una tiene que poder guardarse antes que la otra—;
            // la fila avisa en pantalla mientras falte una de las dos. Lo que
            // sí se impide es cobrar antes de emitir.
            'fecha_pago' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:'.$this->emisionVigente(),
            ],
            'cuenta_bancaria_id' => ['sometimes', 'nullable', Rule::exists('cuentas_bancarias', 'id')],

            'observacion' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Contra qué fecha de emisión se mide el pago: la que viene en esta misma
     * petición si se está corrigiendo, y si no la guardada.
     *
     * Mirar siempre la guardada dejaba pasar un pago anterior a la emisión
     * cuando ambas celdas se mandaban juntas, que es justo lo que la regla
     * quiere impedir.
     */
    private function emisionVigente(): string
    {
        $entrante = $this->date('fecha_emision');

        return $entrante !== null
            ? $entrante->toDateString()
            : $this->factura()->fecha_emision->toDateString();
    }

    private function factura(): Factura
    {
        /** @var Factura $factura */
        $factura = $this->route('factura');

        return $factura;
    }
}
