<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

/**
 * El catálogo de rutas es de programación (el recorrido) y de abastecimiento
 * (la dotación): lo configura quien configura cualquiera de las dos.
 */
class RutaPolicy
{
    public function configurar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionConfigurar)
            || $user->checkPermissionTo(Permiso::AbastecimientoConfigurar);
    }
}
