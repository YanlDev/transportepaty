<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Abastecimiento;
use App\Models\User;

/**
 * El área de abastecimiento: ver el tablero, registrar el combustible que se
 * autoriza y configurar la pizarra de dotaciones son tres permisos aparte,
 * porque la pizarra la decide quien manda en el área, no quien registra.
 */
class AbastecimientoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    public function delete(User $user, Abastecimiento $abastecimiento): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    public function configurar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoConfigurar);
    }
}
