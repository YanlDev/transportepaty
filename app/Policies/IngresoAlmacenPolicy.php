<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\IngresoAlmacen;
use App\Models\User;

class IngresoAlmacenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function view(User $user, IngresoAlmacen $ingreso): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenIngresar);
    }

    /**
     * Anular saca sus movimientos del kardex; el documento queda a la vista.
     */
    public function anular(User $user, IngresoAlmacen $ingreso): bool
    {
        return ! $ingreso->estaAnulado() && $user->checkPermissionTo(Permiso::AlmacenAnular);
    }
}
