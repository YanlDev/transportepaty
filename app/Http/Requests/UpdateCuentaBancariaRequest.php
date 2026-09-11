<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateCuentaBancariaRequest extends CuentaBancariaRequest
{
    protected function reglaCuentaUnica(): Unique
    {
        return Rule::unique('cuentas_bancarias', 'numero_cuenta')
            ->where('banco', $this->string('banco')->value())
            ->ignore($this->route('cuenta'));
    }
}
