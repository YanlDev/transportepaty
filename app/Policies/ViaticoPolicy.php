<?php

namespace App\Policies;

use App\Enums\EstadoVuelta;
use App\Enums\Permiso;
use App\Models\User;
use App\Models\Viatico;

/**
 * Los viáticos los deposita abastecimiento y los corrige facturación: los
 * dos con el mismo permiso. Un viático anulado ya no se toca, ni los de un
 * ticket cerrado (con candado): se reabre para corregirlos.
 */
class ViaticoPolicy
{
    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoViaticos);
    }

    public function update(User $user, Viatico $viatico): bool
    {
        return ! $viatico->estaAnulado()
            && $viatico->vuelta->estado !== EstadoVuelta::Cerrada
            && $user->checkPermissionTo(Permiso::AbastecimientoViaticos);
    }

    public function anular(User $user, Viatico $viatico): bool
    {
        return $this->update($user, $viatico);
    }
}
