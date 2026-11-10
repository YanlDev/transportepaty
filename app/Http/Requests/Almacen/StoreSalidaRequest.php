<?php

namespace App\Http\Requests\Almacen;

use App\Enums\DestinoSalida;
use App\Enums\EstadoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Rules\FechaDelAlmacen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El vale de salida: qué sale, a dónde va y qué técnico lo recibe (siempre:
 * es quien responde por lo que se llevó). Si alcanza el stock lo decide
 * `Kardex`, que conoce el saldo en la fecha del vale.
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
                if ($validator->errors()->isNotEmpty() || $this->input('destino') !== DestinoSalida::OrdenTrabajo->value) {
                    return;
                }

                $orden = OrdenTrabajo::query()->find($this->integer('orden_trabajo_id'));

                if ($orden?->estaAnulado()) {
                    $validator->errors()->add('orden_trabajo_id', "La {$orden->numero()} está anulada: no puede recibir repuestos.");
                } elseif ($orden?->estado === EstadoOrdenTrabajo::Finalizada) {
                    $validator->errors()->add('orden_trabajo_id', "La {$orden->numero()} ya está finalizada: reábrela en Mantenimiento para cargarle más repuestos.");
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
            'orden_trabajo_id.required_if' => 'Elige la orden de trabajo.',
            'vehiculo_id.required_if' => 'Elige la unidad.',
            'entregado_a.required' => 'Anota el técnico que recibe: es quien responde por lo que sale.',
            'entregado_a.min' => 'Anota el nombre del técnico que recibe.',
            'detalles.required' => 'Agrega al menos un artículo.',
            'detalles.*.articulo_id.distinct' => 'Ese artículo ya está en el vale: suma la cantidad en una sola línea.',
            'detalles.*.cantidad.gt' => 'La cantidad tiene que ser mayor a cero.',
        ];
    }
}
