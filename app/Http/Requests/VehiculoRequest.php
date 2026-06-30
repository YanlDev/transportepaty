<?php

namespace App\Http\Requests;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCombustible;
use App\Enums\TipoVehiculo;
use App\Rules\ConductorPerteneceASucursal;
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
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'conductor_id' => [
                'nullable',
                'integer',
                Rule::exists('conductores', 'id'),
                new ConductorPerteneceASucursal($this->input('sucursal_id')),
            ],
            'placa' => ['required', 'string', 'max:10', $this->reglaPlacaUnica()],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'anio' => ['required', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'numero_serie' => ['nullable', 'string', 'max:100', $this->reglaNumeroSerieUnico()],
            'numero_motor' => ['nullable', 'string', 'max:100'],
            'imei' => ['nullable', 'string', 'max:50', $this->reglaImeiUnico()],
            'tipo' => ['required', Rule::enum(TipoVehiculo::class)],
            'combustible' => ['required', Rule::enum(TipoCombustible::class)],
            'estado' => ['required', Rule::enum(EstadoVehiculo::class)],
            'kilometraje' => ['required', 'integer', 'min:0'],
            'fecha_adquisicion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The uniqueness rule for the "placa" column, scoped per request type.
     */
    abstract protected function reglaPlacaUnica(): Unique;

    /**
     * The uniqueness rule for the "numero_serie" column, scoped per request type.
     */
    abstract protected function reglaNumeroSerieUnico(): Unique;

    /**
     * The uniqueness rule for the "imei" column, scoped per request type.
     */
    abstract protected function reglaImeiUnico(): Unique;
}
