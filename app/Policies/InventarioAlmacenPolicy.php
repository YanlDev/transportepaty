<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\InventarioAlmacen;
use App\Models\User;

class InventarioAlmacenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function view(User $user, InventarioAlmacen $inventario): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AlmacenInventariar);
    }

    /**
     * Anotar lo contado y cerrar: solo mientras el conteo está abierto.
     */
    public function update(User $user, InventarioAlmacen $inventario): bool
    {
        return $inventario->estaAbierto() && $user->checkPermissionTo(Permiso::AlmacenInventariar);
    }

    /**
     * Un conteo abierto no movió nada: se descarta. Uno cerrado se anula,
     * con permiso de anular, y sus diferencias salen del kardex.
     */
    public function anular(User $user, InventarioAlmacen $inventario): bool
    {
        if ($inventario->estaAnulado()) {
            return false;
        }

        return $inventario->estaAbierto()
            ? $user->checkPermissionTo(Permiso::AlmacenInventariar)
            : $user->checkPermissionTo(Permiso::AlmacenAnular);
    }
}
