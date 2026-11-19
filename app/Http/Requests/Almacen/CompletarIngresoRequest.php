<?php

namespace App\Http\Requests\Almacen;

use App\Enums\TipoComprobante;
use App\Models\DetalleIngreso;
use App\Models\IngresoAlmacen;
use App\Services\Archivos\ReglasDeArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Completar el comprobante de una recepción que entró sin él. Las mismas
 * reglas que una compra normal —el total tiene que cuadrar y ser lo que el
 * pedido autorizó—, pero lo recibido ya no cambia: solo se pone lo que dice
 * el papel (tipo, número, fecha, precios, descuento y flete).
 */
class CompletarIngresoRequest extends StoreIngresoRequest
{
    /**
     * Las líneas son las que ya entraron; del formulario solo llega el
     * precio de cada una, si el comprobante trae otro.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $ingreso = $this->ingreso();
        $precios = (array) $this->input('precios', []);

        $this->merge([
            'fecha' => $ingreso->fecha->toDateString(),
            'proveedor_id' => $ingreso->proveedor_id,
            'vale_compra_id' => $ingreso->vale_compra_id,
            'moneda' => $ingreso->moneda->value,
            'detalles' => $ingreso->detalles()->orderBy('id')->get()
                ->map(fn (DetalleIngreso $detalle): array => [
                    'articulo_id' => $detalle->articulo_id,
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => $precios[$detalle->id] ?? (float) $detalle->precio_unitario,
                    'bonificacion' => false,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
            'proveedor_id' => ['required', 'integer'],
            'vale_compra_id' => ['nullable', 'integer'],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'serie_numero' => [
                'nullable',
                Rule::requiredIf(fn (): bool => TipoComprobante::tryFrom((string) $this->input('tipo_comprobante'))?->exigeSerieNumero() ?? false),
                'string',
                'max:30',
            ],
            'moneda' => ['required'],
            'precios_con_igv' => ['boolean'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'flete' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'total_comprobante' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'comprobante' => ['required', ...ReglasDeArchivo::imagenOPdf()],
            'precios' => ['nullable', 'array'],
            'precios.*' => ['numeric', 'gt:0', 'max:99999999'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_id' => ['required', 'integer'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'detalles.*.bonificacion' => ['boolean'],
        ];
    }

    /**
     * La recepción que se completa no cuenta como «otra compra» del pedido.
     */
    protected function ingresoQueNoCuenta(): ?int
    {
        return $this->ingreso()->id;
    }

    private function ingreso(): IngresoAlmacen
    {
        /** @var IngresoAlmacen $ingreso */
        $ingreso = $this->route('ingreso');

        return $ingreso;
    }
}
