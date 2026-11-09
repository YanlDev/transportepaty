<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\SalidaAlmacen;
use App\Models\User;

class SalidaAlmacenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function view(User $user, SalidaAlmacen $salida): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenDespachar);
    }

    /**
     * Lo que no se usó vuelve al almacén: lo registra quien recibe.
     */
    public function devolver(User $user, SalidaAlmacen $salida): bool
    {
        return ! $salida->estaAnulado() && $user->checkPermissionTo(Permiso::AlmacenIngresar);
    }

    public function anular(User $user, SalidaAlmacen $salida): bool
    {
        return ! $salida->estaAnulado() && $user->checkPermissionTo(Permiso::AlmacenAnular);
    }
}
