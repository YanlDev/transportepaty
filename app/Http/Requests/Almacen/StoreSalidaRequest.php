<?php

namespace App\Http\Requests\Almacen;

use App\Enums\DestinoSalida;
use App\Enums\EstadoOrdenTrabajo;
use App\Models\Articulo;
use App\Models\OrdenTrabajo;
use App\Rules\FechaDelAlmacen;
use App\Services\MaterialesDeOrden;
use App\Services\RepuestosDeOrden;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La entrega: qué sale, a dónde va y qué técnico lo recibe (siempre:
 * es quien responde por lo que se llevó). Si alcanza el stock lo decide
 * `Kardex`, que conoce el saldo en la fecha de la entrega.
 */
class StoreSalidaRequest extends FormRequest
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
            'fecha' => ['required', 'date', new FechaDelAlmacen],
            'destino' => ['required', Rule::enum(DestinoSalida::class)],
            'orden_trabajo_id' => ['nullable', 'required_if:destino,'.DestinoSalida::OrdenTrabajo->value, 'integer', Rule::exists('ordenes_trabajo', 'id')],
            'vehiculo_id' => ['nullable', 'required_if:destino,'.DestinoSalida::Unidad->value, 'integer', Rule::exists('vehiculos', 'id')],
            'entregado_a' => ['required', 'string', 'min:3', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.articulo_id' => ['required', 'integer', 'distinct', Rule::exists('articulos', 'id')],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999'],
        ];
    }

    /**
     * A una orden finalizada o anulada ya no le sale nada del almacén: lo
     * que consumió quedó fijado al cerrarla. Si faltó algo, se reabre.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $paraOrden = $this->input('destino') === DestinoSalida::OrdenTrabajo->value;
                $orden = $paraOrden ? OrdenTrabajo::query()->find($this->integer('orden_trabajo_id')) : null;

                $this->respetarReservas($validator, $orden);

                if ($orden === null) {
                    return;
                }

                if ($orden?->estaAnulado()) {
                    $validator->errors()->add('orden_trabajo_id', "La {$orden->numero()} está anulada: no puede recibir repuestos.");
                } elseif ($orden?->estado->sinAprobar()) {
                    $validator->errors()->add('orden_trabajo_id', "La {$orden->numero()} todavía es una pre-orden: operaciones tiene que aprobar el requerimiento antes de sacarle repuestos.");
                } elseif ($orden?->estado === EstadoOrdenTrabajo::Finalizada) {
                    $validator->errors()->add('orden_trabajo_id', "La {$orden->numero()} ya está finalizada: reábrela en Mantenimiento para cargarle más repuestos.");
                } elseif ($orden?->aprobado_at !== null) {
                    $this->soloLoPedido($validator, $orden);
                }
            },
        ];
    }

    /**
     * A una orden que pasó por requerimiento se le entrega lo que tiene
     * aprobado (o lo que pide su preventivo), no más. Lo que haga falta de
     * más se pide como adicional, para que quede quién lo aprobó.
     */
    private function soloLoPedido(Validator $validator, OrdenTrabajo $orden): void
    {
        $pendiente = app(RepuestosDeOrden::class)->pendientePorArticulo($orden);

        if ($orden->servicio !== null) {
            foreach (app(MaterialesDeOrden::class)->requeridos($orden->servicio, $orden) as $requerido) {
                if ($requerido['articulo_id'] !== null && $requerido['falta']) {
                    $pendiente[$requerido['articulo_id']] = max(
                        $pendiente[$requerido['articulo_id']] ?? 0.0,
                        (float) ($requerido['cantidad'] ?? PHP_FLOAT_MAX) - $requerido['despachado'],
                    );
                }
            }
        }

        foreach ($this->input('detalles', []) as $i => $linea) {
            $queda = $pendiente[(int) $linea['articulo_id']] ?? 0.0;

            if ((float) $linea['cantidad'] - $queda > 0.0005) {
                $validator->errors()->add("detalles.{$i}.cantidad", $queda <= 0.0005
                    ? "La {$orden->numero()} no tiene pedido ese repuesto (o ya lo recibió). Si lo necesita, que se pida como adicional en la orden."
                    : "A la {$orden->numero()} solo le falta recibir {$this->cifra($queda)} de ese repuesto. Lo demás se pide como adicional en la orden.");
            }
        }
    }

    /**
     * Lo aprobado para una orden abierta queda apartado: no se le entrega a
     * otra orden, a una unidad ni a consumo.
     */
    private function respetarReservas(Validator $validator, ?OrdenTrabajo $orden): void
    {
        $reservado = app(RepuestosDeOrden::class)->reservado($orden?->id);

        if ($reservado === []) {
            return;
        }

        $articulos = Articulo::query()
            ->whereIn('id', array_column($this->input('detalles', []), 'articulo_id'))
            ->pluck('stock', 'id');

        foreach ($this->input('detalles', []) as $i => $linea) {
            $apartado = $reservado[(int) $linea['articulo_id']] ?? null;

            if ($apartado === null) {
                continue;
            }

            $libre = max((float) ($articulos[(int) $linea['articulo_id']] ?? 0) - $apartado['cantidad'], 0);

            if ((float) $linea['cantidad'] - $libre > 0.0005) {
                $validator->errors()->add("detalles.{$i}.cantidad", sprintf(
                    'Hay %s reservado para %s: quedan %s libres.',
                    $this->cifra($apartado['cantidad']),
                    implode(', ', array_unique($apartado['ordenes'])),
                    $this->cifra($libre),
                ));
            }
        }
    }

    private function cifra(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3, '.', ','), '0'), '.');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orden_trabajo_id.required_if' => 'Elige la orden de trabajo.',
            'vehiculo_id.required_if' => 'Elige la unidad.',
            'entregado_a.required' => 'Anota el técnico que recibe: es quien responde por lo que sale.',
            'entregado_a.min' => 'Anota el nombre del técnico que recibe.',
            'detalles.required' => 'Agrega al menos un artículo.',
            'detalles.*.articulo_id.distinct' => 'Ese artículo ya está en la entrega: suma la cantidad en una sola línea.',
            'detalles.*.cantidad.gt' => 'La cantidad tiene que ser mayor a cero.',
        ];
    }
}
