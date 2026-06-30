<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreSucursalRequest extends SucursalRequest
{
    protected function reglaCodigoUnico(): Unique
    {
        return Rule::unique('sucursales', 'codigo');
    }
}
