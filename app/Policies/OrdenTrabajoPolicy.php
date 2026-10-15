<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\OrdenTrabajo;
use App\Models\User;

class OrdenTrabajoPolicy
{
    /**
     * Ver el semáforo de la flota, la ficha de cada unidad y sus órdenes.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoGestionar);
    }

    public function update(User $user, OrdenTrabajo $orden): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoGestionar);
    }

    public function delete(User $user, OrdenTrabajo $orden): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoGestionar);
    }
}
