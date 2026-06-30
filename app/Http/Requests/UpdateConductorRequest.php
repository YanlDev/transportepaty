<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateConductorRequest extends ConductorRequest
{
    protected function reglaDocumentoUnico(): Unique
    {
        return Rule::unique('conductores', 'documento')
            ->ignore($this->route('conductor'));
    }
}
