<?php

namespace App\Http\Requests\Almacen;

use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Models\IngresoAlmacen;
use App\Rules\FechaDelAlmacen;
use App\Services\CostoDeCompra;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Una compra que entra al almacén, con su comprobante tal cual viene: el
 * archivo es obligatorio y el total que se digita tiene que cuadrar con los
 * ítems, el descuento y el flete.
 */
class StoreIngresoRequest extends FormRequest
{
    /** Lo que se tolera entre el total del papel y la suma: el redondeo del IGV por línea. */
    public const TOLERANCIA_TOTAL = 0.10;

    /** El tope del archivo, en KB. Las fotos se achican en el celular antes de subir. */
    public const MAXIMO_KB = 10240;

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
            'fecha' => ['required', 'date', new FechaDelAlmacen],
            'fecha_emision' => ['required', 'date', 'before_or_equal:fecha'],
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'serie_numero' => [
                'nullable',
                Rule::requiredIf(fn (): bool => TipoComprobante::tryFrom((string) $this->input('tipo_comprobante'))?->exigeSerieNumero() ?? false),
                'string',
                'max:30',
            ],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'tipo_cambio' => ['nullable', 'required_if:moneda,'.Moneda::Dolares->value, 'numeric', 'gt:0', 'max:99'],
            'precios_con_igv' => ['boolean'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'flete' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'total_comprobante' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'comprobante' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:'.self::MAXIMO_KB],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.articulo_id' => ['required', 'integer', Rule::exists('articulos', 'id')->where('activo', true)],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'detalles.*.bonificacion' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->noRepetido($validator);
                $this->cuadra($validator);
            },
        ];
    }

    /**
     * Lo que dice el comprobante, calculado como lo guardará el controlador.
     *
     * @return array{suma_items: float, subtotal: float, igv: float, total: float, costo_total: float, lineas: list<array{cantidad_base: float, costo_unitario: float, costo_total: float}>}
     */
    public function calculo(): array
    {
        return app(CostoDeCompra::class)->calcular(
            TipoComprobante::from((string) $this->input('tipo_comprobante')),
            Moneda::from((string) $this->input('moneda')),
            $this->filled('tipo_cambio') ? (float) $this->input('tipo_cambio') : null,
            $this->boolean('precios_con_igv'),
            array_values(array_map(fn (array $linea): array => [
                'cantidad' => (float) $linea['cantidad'],
                'precio_unitario' => (float) $linea['precio_unitario'],
                'factor' => 1.0,
                'bonificacion' => filter_var($linea['bonificacion'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ], (array) $this->input('detalles', []))),
            (float) $this->input('descuento', 0),
            (float) $this->input('flete', 0),
        );
    }

    private function noRepetido(Validator $validator): void
    {
        $clave = IngresoAlmacen::claveComprobante($this->input('serie_numero'));

        if ($clave === null) {
            return;
        }

        $repetido = IngresoAlmacen::query()
            ->vigentes()
            ->where('proveedor_id', $this->integer('proveedor_id'))
            ->where('comprobante_clave', $clave)
            ->first();

        if ($repetido !== null) {
            $validator->errors()->add('serie_numero', "Ese comprobante ya entró en {$repetido->numero()}.");
        }
    }

    /**
     * El total del papel contra lo que suman los ítems, menos el descuento y
     * más el flete. Si no cuadra, algo se digitó mal.
     */
    private function cuadra(Validator $validator): void
    {
        $calculo = $this->calculo();

        if ((float) $this->input('descuento', 0) > $calculo['suma_items'] + (float) $this->input('flete', 0)) {
            $validator->errors()->add('descuento', 'El descuento no puede ser mayor que lo comprado.');

            return;
        }

        $declarado = (float) $this->input('total_comprobante');
        $diferencia = round($declarado - $calculo['total'], 2);

        if (abs($diferencia) > self::TOLERANCIA_TOTAL) {
            $simbolo = Moneda::from((string) $this->input('moneda'))->simbolo();

            $validator->errors()->add('total_comprobante', sprintf(
                'No cuadra: el comprobante dice %1$s %2$s y los ítems dan %1$s %3$s (diferencia de %1$s %4$s). Revisa cantidades, precios, IGV, descuento y flete.',
                $simbolo,
                number_format($declarado, 2),
                number_format($calculo['total'], 2),
                number_format(abs($diferencia), 2),
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_emision.before_or_equal' => 'El comprobante no puede ser posterior al ingreso.',
            'serie_numero.required' => 'Anota la serie y el número del comprobante.',
            'tipo_cambio.required_if' => 'En dólares hace falta el tipo de cambio del día del comprobante.',
            'total_comprobante.required' => 'Anota el total que dice el comprobante.',
            'comprobante.required' => 'Adjunta la foto o el PDF del comprobante.',
            'comprobante.uploaded' => 'El archivo no se pudo subir: probablemente es muy pesado. Prueba con una foto o un PDF más liviano.',
            'comprobante.mimetypes' => 'El comprobante tiene que ser PDF o foto JPG, PNG o WEBP. Si es una foto de iPhone (HEIC), tómala con «Tomar foto» o guárdala como JPG.',
            'comprobante.max' => 'El archivo pesa más de 10 MB.',
            'detalles.required' => 'Agrega al menos un artículo.',
            'detalles.*.articulo_id.required' => 'Elige el artículo.',
            'detalles.*.cantidad.gt' => 'La cantidad tiene que ser mayor a cero.',
            'detalles.*.precio_unitario.gt' => 'Ponle precio. Si es una bonificación, márcala y ponle su valor.',
        ];
    }
}
