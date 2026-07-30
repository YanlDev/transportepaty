<?php

namespace App\Http\Requests;

use App\Models\Viaje;

class StoreViajeRequest extends ViajeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Viaje::class) ?? false;
    }
}
