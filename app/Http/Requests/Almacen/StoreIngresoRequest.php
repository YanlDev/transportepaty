<?php

namespace App\Http\Requests\Almacen;

use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Models\IngresoAlmacen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Una compra que entra al almacén, con su comprobante tal cual viene.
 */
class StoreIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'serie_numero' => $this->filled('serie_numero') ? mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('serie_numero'))) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'serie_numero' => ['nullable', 'required_unless:tipo_comprobante,'.TipoComprobante::SinComprobante->value, 'string', 'max:30'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'tipo_cambio' => ['nullable', 'required_if:moneda,'.Moneda::Dolares->value, 'numeric', 'gt:0', 'max:99'],
            'precios_con_igv' => ['boolean'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'comprobante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.articulo_id' => ['required', 'integer', Rule::exists('articulos', 'id')->where('activo', true)],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('serie_numero') === null) {
                    return;
                }

                $repetido = IngresoAlmacen::query()
                    ->vigentes()
                    ->where('proveedor_id', $this->integer('proveedor_id'))
                    ->where('serie_numero', $this->input('serie_numero'))
                    ->first();

                if ($repetido !== null) {
                    $validator->errors()->add('serie_numero', "Ese comprobante ya entró en {$repetido->numero()}.");
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
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'serie_numero.required_unless' => 'Anota la serie y el número del comprobante.',
            'tipo_cambio.required_if' => 'En dólares hace falta el tipo de cambio del día del comprobante.',
            'detalles.required' => 'Agrega al menos un artículo.',
            'detalles.*.articulo_id.required' => 'Elige el artículo.',
            'detalles.*.cantidad.gt' => 'La cantidad tiene que ser mayor a cero.',
        ];
    }
}
