<?php

namespace App\Policies;

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
        return $user->hasAnyRole(['admin', 'visor', 'contador']);
    }

    public function view(User $user, Viaje $viaje): bool
    {
        return $user->hasAnyRole(['admin', 'visor', 'contador']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Viaje $viaje): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Viaje $viaje): bool
    {
        return $user->hasRole('admin');
    }
}
