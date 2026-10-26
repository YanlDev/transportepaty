<?php

namespace App\Http\Requests;

use App\Enums\TipoVehiculo;
use App\Models\Ajuste;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Lo que abastecimiento autorizó para una unidad. Con «tanque lleno» los
 * galones se pueden dejar vacíos si ya está configurado cuánto es un tanque
 * lleno.
 */
class RegistrarAbastecimientoRequest extends FormRequest
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
            'vehiculo_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value)],
            'vuelta_id' => ['nullable', Rule::exists('vueltas', 'id')],
            'punto_abastecimiento_id' => ['nullable', Rule::exists('puntos_abastecimiento', 'id')],
            'fecha' => ['required', 'date'],
            'tanque_lleno' => ['boolean'],
            'galones' => ['nullable', 'numeric', 'min:0.1', 'max:999'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('galones') || ($this->boolean('tanque_lleno') && Ajuste::valor(Ajuste::GALONES_TANQUE_LLENO) !== null)) {
                    return;
                }

                $validator->errors()->add('galones', $this->boolean('tanque_lleno')
                    ? 'Escribe los galones: todavía no está configurado cuánto es un tanque lleno.'
                    : 'Escribe cuántos galones se autorizaron.');
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehiculo_id' => 'unidad',
            'punto_abastecimiento_id' => 'punto',
        ];
    }
}
