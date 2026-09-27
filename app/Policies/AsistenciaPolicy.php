<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Asistencia;
use App\Models\User;

class AsistenciaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AsistenciaVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AsistenciaMarcar);
    }

    public function update(User $user, Asistencia $asistencia): bool
    {
        return $user->checkPermissionTo(Permiso::AsistenciaMarcar);
    }

    /**
     * Borrar una marca es desmarcar el día, no corregirlo. El controlador
     * autorizaba esto con `update` porque acá no había `delete`, y el permiso
     * terminaba diciendo una cosa distinta de la que hacía.
     */
    public function delete(User $user, Asistencia $asistencia): bool
    {
        return $user->checkPermissionTo(Permiso::AsistenciaMarcar);
    }
}
