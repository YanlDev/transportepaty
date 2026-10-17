<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

class ParametroMantenimientoPolicy
{
    /**
     * Cambiar los cortes recolorea las llantas de toda la flota: va con la
     * configuración de los planes, no con el llantero.
     */
    public function update(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoConfigurar);
    }
}
