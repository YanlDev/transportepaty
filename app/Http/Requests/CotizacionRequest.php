<?php

namespace App\Http\Requests;

use App\Enums\EstadoCotizacion;
use App\Models\Cotizacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sirve para el alta y para la edición: el número de cotización lo pone el
 * sistema y los importes se recalculan en el servidor, así que no hay ningún
 * campo cuya regla dependa de si el registro ya existe.
 */
class CotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sin retorno vacío no se manda nada, y la columna espera un número.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'km_retorno' => $this->input('km_retorno') ?? 0,
            'dias_retorno' => $this->input('dias_retorno') ?? 0,
            'cantidad' => $this->input('cantidad') ?? 1,
            'unidad' => $this->input('unidad') ?? 'VIAJE',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'valido_hasta' => ['required', 'date', 'after_or_equal:fecha'],
            // El cliente puede no estar en el padrón: a un particular se le
            // cotiza antes de darlo de alta, y el nombre alcanza para emitir.
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_ruc' => ['nullable', 'digits:11'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'punto_partida_id' => ['nullable', 'exists:puntos_traslado,id'],
            'punto_llegada_id' => ['nullable', 'exists:puntos_traslado,id'],
            'origen' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'km' => ['required', 'integer', 'min:1'],
            // Admite medios días: hay rutas que se cotizan en 1.5 o 5.5 días,
            // y redondear hacia arriba encarece la tarifa sin motivo.
            'dias' => ['required', 'numeric', 'min:0.5'],
            // El regreso sin carga: cero cuando la vuelta trae otra carga
            // que paga su propio flete.
            'km_retorno' => ['nullable', 'integer', 'min:0'],
            'dias_retorno' => ['nullable', 'numeric', 'min:0'],
            // Es margen sobre el precio de venta: la tarifa es el costo entre
            // (1 − margen), así que un margen de 100 % no tiene tarifa posible.
            'margen_pct' => ['required', 'numeric', 'min:0', 'max:0.9'],
            // 30 TN a S/ 435 o 1 viaje a S/ 9,000. Sin precio unitario se
            // cobra la tarifa calculada repartida entre la cantidad.
            'cantidad' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'unidad' => ['required', Rule::in(array_keys(Cotizacion::UNIDADES))],
            'precio_unitario' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'estado' => ['required', Rule::enum(EstadoCotizacion::class)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
