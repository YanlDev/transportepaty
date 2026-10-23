<?php

namespace App\Http\Requests;

use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Un conteo en base: de cada ítem del catálogo, cuántas buenas y cuántas
 * dañadas. Solo carretas.
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

                if ($vehiculo instanceof Vehiculo && $vehiculo->tipo !== TipoVehiculo::Carreta) {
                    $validator->errors()->add('items', 'El equipamiento se cuenta solo en carretas.');
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
