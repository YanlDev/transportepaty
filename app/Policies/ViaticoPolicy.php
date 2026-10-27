<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;
use App\Models\Viatico;

/**
 * Los viáticos los deposita abastecimiento y los corrige facturación: los
 * dos con el mismo permiso. Un viático anulado ya no se toca.
 */
class ViaticoPolicy
{
    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoViaticos);
    }

    public function update(User $user, Viatico $viatico): bool
    {
        return ! $viatico->estaAnulado() && $user->checkPermissionTo(Permiso::AbastecimientoViaticos);
    }

    public function anular(User $user, Viatico $viatico): bool
    {
        return $this->update($user, $viatico);
    }
}
