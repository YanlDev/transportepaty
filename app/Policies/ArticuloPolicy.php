<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Articulo;
use App\Models\User;

class ArticuloPolicy
{
    /**
     * El stock y el kardex: lo ve quien entra al almacén, también el taller.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function view(User $user, Articulo $articulo): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenCatalogo);
    }

    public function update(User $user, Articulo $articulo): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenCatalogo);
    }

    /**
     * Los costos, el valor del inventario y el kardex valorizado: no todo el
     * que ve el stock tiene que ver cuánto vale.
     */
    public function verCostos(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVerCostos);
    }
}
