<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreVehiculoRequest extends VehiculoRequest
{
    protected function reglaPlacaUnica(): Unique
    {
        return Rule::unique('vehiculos', 'placa');
    }
}
