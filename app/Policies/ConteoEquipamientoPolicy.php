<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\ConteoEquipamiento;
use App\Models\User;

class ConteoEquipamientoPolicy
{
    /**
     * Ver el equipamiento de las carretas: va con el resto de mantenimiento.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoEquipamiento);
    }

    /**
     * Borrar un conteo mal hecho: la carreta vuelve al conteo anterior.
     */
    public function delete(User $user, ConteoEquipamiento $conteo): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoEquipamiento);
    }

    /**
     * El catálogo y sus mínimos valen para todas las carretas: va con la
     * configuración de los planes.
     */
    public function configurar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoConfigurar);
    }
}
