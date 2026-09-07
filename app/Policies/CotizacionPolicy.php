<?php

namespace App\Policies;

use App\Models\Cotizacion;
use App\Models\User;

/**
 * El visor puede consultar una tarifa ya armada —la necesita para responderle
 * a un cliente—, pero ponerle precio a un viaje es del admin.
 */
class CotizacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, Cotizacion $cotizacion): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Cotizacion $cotizacion): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Cotizacion $cotizacion): bool
    {
        return $user->hasRole('admin');
    }
}
