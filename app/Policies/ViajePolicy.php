<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viaje;

class ViajePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, Viaje $viaje): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
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
