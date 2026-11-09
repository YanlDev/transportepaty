<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Enums\TipoIngreso;
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
     * Cambiar la foto o el PDF de una compra: lo hace quien registra compras.
     * No toca el kardex, así que vale también en un mes cerrado.
     */
    public function reemplazarComprobante(User $user, IngresoAlmacen $ingreso): bool
    {
        return $ingreso->tipo === TipoIngreso::Compra
            && ! $ingreso->estaAnulado()
            && $user->checkPermissionTo(Permiso::AlmacenIngresar);
    }

    /**
     * Anular saca sus movimientos del kardex; el documento queda a la vista.
     */
    public function anular(User $user, IngresoAlmacen $ingreso): bool
    {
        return ! $ingreso->estaAnulado() && $user->checkPermissionTo(Permiso::AlmacenAnular);
    }
}
