<?php

namespace App\Http\Requests\Almacen;

use App\Enums\DestinoSalida;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El vale de salida: qué sale y a dónde va. Si alcanza el stock lo decide
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
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'destino' => ['required', Rule::enum(DestinoSalida::class)],
            'orden_trabajo_id' => ['nullable', 'required_if:destino,'.DestinoSalida::OrdenTrabajo->value, 'integer', Rule::exists('ordenes_trabajo', 'id')],
            'vehiculo_id' => ['nullable', 'required_if:destino,'.DestinoSalida::Unidad->value, 'integer', Rule::exists('vehiculos', 'id')],
            'entregado_a' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.articulo_id' => ['required', 'integer', 'distinct', Rule::exists('articulos', 'id')],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'orden_trabajo_id.required_if' => 'Elige la orden de trabajo.',
            'vehiculo_id.required_if' => 'Elige la unidad.',
            'detalles.required' => 'Agrega al menos un artículo.',
            'detalles.*.articulo_id.distinct' => 'Ese artículo ya está en el vale: suma la cantidad en una sola línea.',
            'detalles.*.cantidad.gt' => 'La cantidad tiene que ser mayor a cero.',
        ];
    }
}
