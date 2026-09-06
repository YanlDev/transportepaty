<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateClienteRequest extends ClienteRequest
{
    protected function reglaRucUnico(): Unique
    {
        return Rule::unique('clientes', 'ruc')->ignore($this->route('cliente'));
    }
}
