<?php

namespace App\Http\Requests;

use App\Models\Asignacion;

class StoreAsignacionRequest extends AsignacionRequest
{
    /**
     * Al registrar no hay nada que ignorar: la asignación todavía no existe. La
     * fecha de inicio tampoco se pide, la estampa el controlador.
     */
    protected function asignacionEnEdicion(): ?Asignacion
    {
        return null;
    }
}
