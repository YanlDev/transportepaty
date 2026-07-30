<?php

namespace App\Policies;

use App\Models\Novedad;
use App\Models\User;

class NovedadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, Novedad $novedad): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Novedad $novedad): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Novedad $novedad): bool
    {
        return $user->hasRole('admin');
    }
}
