<?php

namespace App\Http\Requests;

use App\Enums\TipoVehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlantillaMantenimientoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_mantenimiento' => ['required', 'string', 'max:50'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'tipo_vehiculo' => ['nullable', Rule::enum(TipoVehiculo::class)],
            'intervalo_km' => ['nullable', 'integer', 'min:1'],
            'intervalo_meses' => ['nullable', 'integer', 'min:1'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'costo_estimado' => ['nullable', 'numeric', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('intervalo_km') === null && $this->input('intervalo_meses') === null) {
                $validator->errors()->add(
                    'intervalo_km',
                    'Define al menos un intervalo: por kilómetros o por meses.',
                );
            }
        });
    }
}
