<?php

namespace App\Policies;

use App\Models\Sucursal;
use App\Models\User;

class SucursalPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sucursal $sucursal): bool
    {
        return $user->hasAnyRole(['admin', 'visor']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sucursal $sucursal): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sucursal $sucursal): bool
    {
        return $user->hasRole('admin');
    }
}
