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
            // factura sin fecha de emisión no existe. Tampoco puede quedar
            // después de un cobro ya registrado.
            'fecha_emision' => ['sometimes', 'required', 'date', ...$this->noDespuesDeLosCobros()],
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

            // La otra mitad del cobro: lo que el cliente depositó en la cuenta
            // de detracciones del Banco de la Nación.
            'fecha_detraccion' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:'.$this->emisionVigente(),
            ],
            'constancia_detraccion' => ['sometimes', 'nullable', 'string', 'max:30'],

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

    /**
     * La emisión no puede quedar después de un cobro ya guardado, del neto o
     * de la detracción. Sin esto, corregir la emisión dejaba un pago anterior
     * a la factura, que es lo que la regla de las fechas de pago impide.
     *
     * Un cobro que llega en la misma petición se mide por su propia regla.
     *
     * @return array<int, string>
     */
    private function noDespuesDeLosCobros(): array
    {
        $factura = $this->factura();

        return collect(['fecha_pago', 'fecha_detraccion'])
            ->reject(fn (string $campo): bool => $this->has($campo))
            ->map(fn (string $campo) => $factura->{$campo})
            ->filter()
            ->map(fn ($fecha): string => 'before_or_equal:'.$fecha->toDateString())
            ->values()
            ->all();
    }

    private function factura(): Factura
    {
        /** @var Factura $factura */
        $factura = $this->route('factura');

        return $factura;
    }
}
