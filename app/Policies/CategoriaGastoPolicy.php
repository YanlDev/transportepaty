<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

class CategoriaGastoPolicy
{
    /**
     * Las categorías las lleva gerencia: crearlas, renombrarlas y
     * desactivarlas.
     */
    public function administrar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::GastosCategorias);
    }
}
