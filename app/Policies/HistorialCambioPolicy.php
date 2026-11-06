<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

/**
 * El historial se lee y nada más: nadie lo edita ni lo borra.
 */
class HistorialCambioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::HistorialVer);
    }
}
