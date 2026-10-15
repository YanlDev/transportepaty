<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\LecturaKilometraje;
use App\Models\User;

class LecturaKilometrajePolicy
{
    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoRegistrarKm);
    }

    /**
     * Borrar una lectura es corregir un dedazo: lo hace quien registra km.
     */
    public function delete(User $user, LecturaKilometraje $lectura): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoRegistrarKm);
    }
}
