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
     * Quitar una lectura cambia el km de la unidad y con él cuándo le toca
     * su servicio: es una corrección, y la hace quien tiene ese permiso (el
     * administrador). Quién la quitó y qué decía queda en el historial.
     */
    public function delete(User $user, LecturaKilometraje $lectura): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoCorregirKm);
    }
}
