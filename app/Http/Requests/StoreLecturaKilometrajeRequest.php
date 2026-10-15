<?php

namespace App\Http\Requests;

use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use App\Services\ProximoMantenimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El km que se anota al llegar la unidad a base. Solo tractos: la carreta
 * no tiene odómetro.
 */
class StoreLecturaKilometrajeRequest extends FormRequest
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
            'vehiculo_id' => [
                'required',
                'integer',
                Rule::exists('vehiculos', 'id')
                    ->where('tipo', TipoVehiculo::Tracto->value)
                    ->whereNull('deleted_at'),
            ],
            'kilometraje' => ['required', 'integer', 'min:1', 'max:9999999'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * El odómetro no retrocede: un km menor que el último conocido es un
     * error de tipeo o de placa, y si pasara aceptado haría que la unidad
     * pareciera al día cuando no lo está.
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

                $vehiculo = Vehiculo::query()->findOrFail($this->integer('vehiculo_id'));
                $ultimo = app(ProximoMantenimiento::class)->paraVehiculo($vehiculo)['km_actual'];

                if ($ultimo !== null && $this->integer('kilometraje') < $ultimo) {
                    $validator->errors()->add(
                        'kilometraje',
                        'El último km de '.$vehiculo->placa.' es '.number_format($ultimo, 0, '.', ',').': el nuevo no puede ser menor.',
                    );
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
            'vehiculo_id.exists' => 'Elige un tracto activo.',
        ];
    }
}
