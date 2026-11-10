<?php

namespace App\Http\Requests;

use App\Models\ItemEquipamiento;
use App\Models\Vehiculo;
use App\Services\EquipamientoDeUnidades;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Contar el equipamiento de una unidad en base.
 *
 * El primer conteo de un ítem es su saldo inicial: lo que la unidad ya
 * tenía. Desde ahí el conteo comprueba, no agrega: no puede haber más de lo
 * asignado, porque lo nuevo sale del almacén con vale o llega de otra unidad.
 */
class StoreConteoEquipamientoRequest extends FormRequest
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
            'observacion' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('items_equipamiento', 'id')->where('activo', true)],
            'items.*.buenas' => ['required', 'integer', 'min:0', 'max:999'],
            'items.*.danadas' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $vehiculo = $this->route('vehiculo');

                if (! $vehiculo instanceof Vehiculo || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $filas = collect(app(EquipamientoDeUnidades::class)->paraVehiculo($vehiculo)['items'])->keyBy('id');
                $nombres = ItemEquipamiento::query()->pluck('nombre', 'id');

                foreach ((array) $this->input('items', []) as $indice => $item) {
                    $fila = $filas->get((int) $item['id']);

                    if ($fila === null) {
                        $validator->errors()->add("items.{$indice}.id", "{$nombres->get((int) $item['id'])} no es equipamiento de esta unidad.");

                        continue;
                    }

                    $contado = (int) $item['buenas'] + (int) $item['danadas'];

                    if ($fila['con_historial'] && $contado > $fila['asignado']) {
                        $validator->errors()->add(
                            "items.{$indice}.buenas",
                            "{$fila['nombre']}: la unidad tiene asignadas {$fila['asignado']} y se contaron {$contado}. Lo que se agrega sale del almacén con vale o llega de otra unidad con un traslado.",
                        );
                    }
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
            'items.*.buenas.required' => 'Anota cuántas hay, aunque sea 0.',
            'items.*.danadas.required' => 'Anota cuántas dañadas hay, aunque sea 0.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
