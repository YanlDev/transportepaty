<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

class DescansoDebidoPolicy
{
    public function update(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AsistenciaAjustar);
    }
}
