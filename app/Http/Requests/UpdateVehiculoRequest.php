<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateVehiculoRequest extends VehiculoRequest
{
    protected function reglaPlacaUnica(): Unique
    {
        return Rule::unique('vehiculos', 'placa')->ignore($this->route('vehiculo'));
    }
}
