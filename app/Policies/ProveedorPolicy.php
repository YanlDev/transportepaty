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

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenCatalogo);
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenCatalogo);
    }
}
