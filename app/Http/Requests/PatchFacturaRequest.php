<?php

namespace App\Http\Requests;

use App\Enums\Moneda;
use App\Models\Factura;
use Closure;
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
            // El valor del flete, sin IGV. El IGV, el total, la detracción y
            // el neto se calculan solos.
            'monto' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            // El total con IGV, para el flete que se pactó con el IGV adentro:
            // el valor sale dividiendo.
            'total' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99', 'prohibits:monto,monto_por_viaje'],
            // El precio de cada GR, para las facturas que se pactan por viaje
            // (Minsur): el total se calcula multiplicando. Lo que se guarda
            // sigue siendo el total, que es lo que dice la factura.
            'monto_por_viaje' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99', 'prohibits:monto'],
            'moneda' => ['sometimes', 'required', Rule::enum(Moneda::class)],
            // Con o sin céntimos en la detracción: depende de cómo la emite
            // el cliente (Minsur, con céntimos).
            'detraccion_redondeada' => ['sometimes', 'boolean'],
            // La detracción a mano, cuando el cálculo no coincide con lo que
            // depositó el cliente. Vacía vuelve al cálculo. Va sola: con el
            // valor o el total en la misma petición no se sabría cuál manda.
            'detraccion' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'prohibits:monto,total,monto_por_viaje,detraccion_redondeada',
                $this->detraccionDentroDelTotal(...),
            ],

            // La fecha de pago no exige la cuenta acá —se cargan en celdas
            // distintas y una tiene que poder guardarse antes que la otra—;
            // la fila avisa en pantalla mientras falte una de las dos. Puede
            // ser anterior a la emisión: hay clientes que pagan el 20 y la
            // factura recién sale el 22; la fila lo marca.
            'fecha_pago' => ['sometimes', 'nullable', 'date'],
            'cuenta_bancaria_id' => ['sometimes', 'nullable', Rule::exists('cuentas_bancarias', 'id')],

            // La otra mitad del cobro: lo que el cliente depositó en la cuenta
            // de detracciones del Banco de la Nación. Como el neto, puede
            // llegar antes de que se emita la factura.
            'fecha_detraccion' => ['sometimes', 'nullable', 'date'],
            'constancia_detraccion' => ['sometimes', 'nullable', 'string', 'max:30'],

            'observacion' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // «Facturado a»: el cliente impreso en la factura, que puede no
            // ser el de la GR.
            'cliente_razon_social' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cliente_ruc' => ['sometimes', 'nullable', 'digits:11'],
        ];
    }

    /**
     * La detracción a mano necesita un total del que descontarse y no puede
     * pasarlo.
     */
    private function detraccionDentroDelTotal(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $factura = $this->factura();

        if ($factura->total === null) {
            $fail('Primero carga el valor o el total de la factura.');

            return;
        }

        if ((float) $value > (float) $factura->total) {
            $fail('La detracción no puede pasar el total de la factura.');
        }
    }

    private function factura(): Factura
    {
        /** @var Factura $factura */
        $factura = $this->route('factura');

        return $factura;
    }
}
