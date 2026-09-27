<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;
use App\Models\Viaje;

class ViajePolicy
{
    /**
     * El contador lee los viajes —son la contrapartida de lo que factura—
     * pero no los crea ni los edita: eso sale de la GR, no de la cobranza.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ViajesVer);
    }

    public function view(User $user, Viaje $viaje): bool
    {
        return $user->checkPermissionTo(Permiso::ViajesVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ViajesRegistrar);
    }

    public function update(User $user, Viaje $viaje): bool
    {
        return $user->checkPermissionTo(Permiso::ViajesEditar);
    }

    public function delete(User $user, Viaje $viaje): bool
    {
        return $user->checkPermissionTo(Permiso::ViajesEliminar);
    }

    /**
     * Anular una GR mala —y reactivarla si se anuló por error— es un permiso
     * aparte de corregir el viaje: saca el viaje de la meta y de la cobranza.
     */
    public function anular(User $user, Viaje $viaje): bool
    {
        return $user->checkPermissionTo(Permiso::ViajesAnular);
    }
}
