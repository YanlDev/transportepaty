<?php

namespace App\Policies;

use App\Models\Programacion;
use App\Models\User;

class ProgramacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Programacion $programacion): bool
    {
        return $user->hasRole('admin');
    }
}
