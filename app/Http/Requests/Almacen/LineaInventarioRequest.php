<?php

namespace App\Http\Requests\Almacen;

use App\Models\MovimientoAlmacen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Lo contado de un artículo. Si el artículo ya está en el conteo, se
 * reemplaza: se recuenta, no se suma.
 *
 * El primer conteo de un artículo es su saldo inicial: sin costo entraría a
 * S/ 0 y todo lo que saliera después saldría gratis. Por eso ahí el costo es
 * obligatorio.
 */
class LineaInventarioRequest extends FormRequest
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
            'articulo_id' => ['required', 'integer', Rule::exists('articulos', 'id')],
            'cantidad_contada' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'costo_estimado' => ['boolean'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || (float) $this->input('cantidad_contada') <= 0) {
                    return;
                }

                $esSaldoInicial = MovimientoAlmacen::query()->where('articulo_id', $this->integer('articulo_id'))->doesntExist();

                if ($esSaldoInicial && (float) $this->input('costo_unitario', 0) <= 0) {
                    $validator->errors()->add('costo_unitario', 'Es su saldo inicial: anota el costo unitario (el de su última factura o uno estimado).');
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
            'articulo_id.required' => 'Elige el artículo.',
            'cantidad_contada.required' => 'Anota cuánto hay, aunque sea 0.',
        ];
    }
}
