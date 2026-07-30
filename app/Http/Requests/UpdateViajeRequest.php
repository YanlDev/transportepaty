<?php

namespace App\Http\Requests;

class UpdateViajeRequest extends ViajeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('viaje')) ?? false;
    }
}
