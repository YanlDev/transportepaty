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
        return ! $orden->estaAnulado() && $user->checkPermissionTo(Permiso::MantenimientoGestionar);
    }

    /**
     * Una orden no se borra: se anula, y eso deja un hueco en el plan de la
     * unidad, así que tiene su propio permiso.
     */
    public function anular(User $user, OrdenTrabajo $orden): bool
    {
        return ! $orden->estaAnulado() && $user->checkPermissionTo(Permiso::MantenimientoAnular);
    }
}
