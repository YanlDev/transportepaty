<?php

namespace App\Http\Requests;

use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes a registrar y corregir un viaje.
 *
 * Los números de guía se piden opcionales porque no siempre llegan a la vez que
 * el viaje: primero sale la unidad y después aparece el papel. La ruta, en
 * cambio, sí es obligatoria: un viaje sin origen ni destino no es un viaje.
 */
abstract class ViajeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracto_id' => [
                'required',
                Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value),
            ],
            'carreta_id' => [
                'nullable',
                Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Carreta->value),
            ],
            'conductor_id' => ['nullable', 'exists:conductores,id'],

            'tipo_carga' => ['required', Rule::enum(TipoCarga::class)],

            'origen_id' => ['required', 'exists:ubicaciones,id'],
            'destino_id' => ['required', 'exists:ubicaciones,id', 'different:origen_id'],

            'fecha_salida' => ['required', 'date'],
            'fecha_llegada' => ['nullable', 'date', 'after_or_equal:fecha_salida'],

            'numero_guia_remitente' => ['nullable', 'string', 'max:60'],
            'numero_guia_transportista' => ['nullable', 'string', 'max:60'],

            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tracto_id' => 'tracto',
            'carreta_id' => 'carreta',
            'conductor_id' => 'conductor',
            'tipo_carga' => 'tipo de carga',
            'origen_id' => 'origen',
            'destino_id' => 'destino',
            'numero_guia_remitente' => 'guía del remitente',
            'numero_guia_transportista' => 'guía del transportista',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destino_id.different' => 'El destino no puede ser el mismo punto que el origen.',
            'fecha_llegada.after_or_equal' => 'La llegada no puede ser anterior a la salida.',
        ];
    }
}
