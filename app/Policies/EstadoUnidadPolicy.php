<?php

namespace App\Policies;

use App\Models\EstadoUnidad;
use App\Models\User;

class EstadoUnidadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function view(User $user, EstadoUnidad $estadoUnidad): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, EstadoUnidad $estadoUnidad): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, EstadoUnidad $estadoUnidad): bool
    {
        return $user->hasRole('admin');
    }
}
