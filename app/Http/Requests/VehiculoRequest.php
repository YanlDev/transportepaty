<?php

namespace App\Http\Requests;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoVehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

abstract class VehiculoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placa' => ['required', 'string', 'max:10', $this->reglaPlacaUnica()],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'anio' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'tipo' => ['required', Rule::enum(TipoVehiculo::class)],
            'estado' => ['required', Rule::enum(EstadoVehiculo::class)],
            // Solo el tracto lleva transmisión; en la carreta el campo se descarta.
            'caja' => ['nullable', 'exclude_unless:tipo,'.TipoVehiculo::Tracto->value, Rule::enum(TipoCaja::class)],
            'vin' => ['nullable', 'string', 'max:100'],
            'numero_motor' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'ejes' => ['nullable', 'integer', 'min:1', 'max:10'],
            'peso_neto' => ['nullable', 'integer', 'min:0'],
            'peso_bruto' => ['nullable', 'integer', 'min:0'],
            'carga_util' => ['nullable', 'integer', 'min:0'],
            'fecha_adquisicion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The uniqueness rule for the "placa" column, scoped per request type.
     */
    abstract protected function reglaPlacaUnica(): Unique;
}
