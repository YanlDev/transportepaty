<?php

namespace App\Http\Requests;

use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Las mismas reglas que el alta, salvo la unidad: una orden no se mueve de
 * unidad, se anula y se registra en la correcta.
 */
class UpdateOrdenTrabajoRequest extends StoreOrdenTrabajoRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglasComunes();
    }

    protected function vehiculo(): Vehiculo
    {
        /** @var OrdenTrabajo $orden */
        $orden = $this->route('orden');

        return $orden->vehiculo;
    }
}
