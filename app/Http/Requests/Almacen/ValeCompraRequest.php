<?php

namespace App\Http\Requests\Almacen;

use App\Enums\Moneda;
use App\Enums\TipoValeCompra;
use App\Models\OrdenTrabajo;
use App\Models\ValeCompra;
use App\Services\Archivos\ReglasDeArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Pedir una compra (o corregir el pedido): a quién, qué, a cuánto y por qué.
 *
 * Una compra normal necesita sustento del precio: al menos una cotización
 * adjunta o, si no la hay, decir cómo se verificó. Una emergencia en ruta no
 * espera cotizaciones, pero dice para qué unidad es.
 *
 * Una línea sin artículo es el servicio de un tercero y lleva su descripción.
 */
class ValeCompraRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoValeCompra::class)],
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'sustento_precio' => ['nullable', 'string', 'min:10', 'max:1000'],
            'orden_trabajo_id' => ['nullable', 'integer', Rule::exists('ordenes_trabajo', 'id')],
            'vehiculo_id' => ['nullable', 'integer', Rule::exists('vehiculos', 'id')],
            'cotizaciones' => ['nullable', 'array', 'max:5'],
            'cotizaciones.*' => ReglasDeArchivo::imagenOPdf(),
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.es_servicio' => ['boolean'],
            'detalles.*.articulo_id' => ['nullable', 'integer', 'distinct', Rule::exists('articulos', 'id')->where('activo', true)],
            'detalles.*.descripcion' => ['nullable', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gt:0', 'max:99999999'],
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

                $this->validarLineas($validator);
                $this->validarDestino($validator);
                $this->validarSustento($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Elige a qué proveedor se le va a comprar.',
            'motivo.required' => 'Di para qué es la compra.',
            'motivo.min' => 'Explica un poco más para qué es la compra.',
            'sustento_precio.min' => 'Explica un poco más cómo se verificó el precio.',
            'detalles.required' => 'Agrega al menos una línea.',
            'detalles.*.articulo_id.distinct' => 'Ese artículo ya está en el vale: suma la cantidad en una sola línea.',
            'detalles.*.cantidad.gt' => 'La cantidad tiene que ser mayor a cero.',
            'detalles.*.precio_unitario.gt' => 'Ponle el precio que se va a pagar.',
            'cotizaciones.max' => 'Hasta :max cotizaciones por vale.',
            'cotizaciones.*.mimetypes' => 'Cada cotización tiene que ser un PDF o una foto (JPG, PNG, WEBP o HEIC).',
            'cotizaciones.*.max' => 'Cada cotización debe pesar menos de 10 MB.',
        ];
    }

    private function esServicio(mixed $linea): bool
    {
        return is_array($linea) && filter_var($linea['es_servicio'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function validarLineas(Validator $validator): void
    {
        $hayServicio = false;

        foreach ((array) $this->input('detalles', []) as $indice => $linea) {
            if ($this->esServicio($linea)) {
                $hayServicio = true;

                if (blank($linea['descripcion'] ?? null)) {
                    $validator->errors()->add("detalles.{$indice}.descripcion", 'Describe el servicio que hará el tercero.');
                }
            } elseif (blank($linea['articulo_id'] ?? null)) {
                $validator->errors()->add("detalles.{$indice}.articulo_id", 'Elige el artículo. Si no está en el catálogo, regístralo primero.');
            }
        }

        // El costo de un servicio va directo a la orden, en soles: no pasa
        // por el kardex, que es donde se convierten los dólares.
        if ($hayServicio && $this->input('moneda') !== Moneda::Soles->value) {
            $validator->errors()->add('moneda', 'Un vale con servicios de terceros va en soles.');
        }
    }

    private function validarDestino(Validator $validator): void
    {
        $orden = $this->filled('orden_trabajo_id') ? OrdenTrabajo::query()->find($this->integer('orden_trabajo_id')) : null;

        if ($orden?->estaAnulado()) {
            $validator->errors()->add('orden_trabajo_id', "La {$orden->numero()} está anulada.");
        }

        if ($this->input('tipo') === TipoValeCompra::Emergencia->value && $orden === null && ! $this->filled('vehiculo_id')) {
            $validator->errors()->add('vehiculo_id', 'Una emergencia en ruta es para una unidad: elige cuál.');
        }
    }

    private function validarSustento(Validator $validator): void
    {
        if ($this->input('tipo') !== TipoValeCompra::Normal->value) {
            return;
        }

        $vale = $this->route('vale');
        $yaTiene = $vale instanceof ValeCompra && $vale->getMedia('cotizaciones')->isNotEmpty();

        if (! $yaTiene && ! $this->hasFile('cotizaciones') && ! $this->filled('sustento_precio')) {
            $validator->errors()->add(
                'sustento_precio',
                'Adjunta al menos una cotización o, si no hay, explica cómo se verificó el precio (última compra, lista de precios, llamada al proveedor).',
            );
        }
    }
}
