<?php

namespace App\Policies;

use App\Models\Conductor;
use App\Models\User;

class ConductorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, Conductor $conductor): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Conductor $conductor): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Conductor $conductor): bool
    {
        return $user->hasRole('admin');
    }
}
