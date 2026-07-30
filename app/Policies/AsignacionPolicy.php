<?php

namespace App\Policies;

use App\Models\Asignacion;
use App\Models\User;

class AsignacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, Asignacion $asignacion): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Asignacion $asignacion): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Asignacion $asignacion): bool
    {
        return $user->hasRole('admin');
    }
}
