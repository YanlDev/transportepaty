<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * Mismo criterio que el padrón de conductores: el visor consulta, el admin
 * mantiene.
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('admin');
    }
}
