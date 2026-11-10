<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    /**
     * Lo registra quien mantiene el catálogo, y también quien pide una
     * compra: el pedido tiene que nombrar a su proveedor, aunque sea nuevo.
     */
    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenCatalogo)
            || $user->checkPermissionTo(Permiso::ComprasSolicitar);
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenCatalogo);
    }
}
