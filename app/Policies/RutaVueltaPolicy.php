<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\RutaVuelta;
use App\Models\User;

/**
 * Las rutas de un ticket se eligen al programarlo; lo único que se completa
 * después es la variante por peso.
 */
class RutaVueltaPolicy
{
    /**
     * Elegir la variante (por peso) de una ruta del ticket: lo hace quien
     * programa o quien autoriza el combustible, mientras el ticket siga
     * abierto.
     */
    public function elegirVariante(User $user, RutaVuelta $rutaVuelta): bool
    {
        return $rutaVuelta->vuelta->estaAbierta()
            && ($user->checkPermissionTo(Permiso::ProgramacionEditar)
                || $user->checkPermissionTo(Permiso::AbastecimientoRegistrar));
    }
}
