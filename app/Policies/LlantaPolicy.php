<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

class LlantaPolicy
{
    /**
     * Ver las llantas de la flota: va con el resto de mantenimiento.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoVer);
    }

    /**
     * Instalar, medir, rotar, reparar o retirar: lo hace el llantero.
     */
    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoLlantas);
    }
}
