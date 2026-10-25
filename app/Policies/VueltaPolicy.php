<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

/**
 * Las vueltas son la programación vista de punta a punta: las lee quien ve
 * la programación.
 */
class VueltaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionVer);
    }
}
