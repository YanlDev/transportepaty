<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehiculo;

class VehiculoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor', 'conductor']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * Drivers may only view vehicles assigned to them.
     */
    public function view(User $user, Vehiculo $vehiculo): bool
    {
        if ($user->hasAnyRole(['admin', 'visor'])) {
            return true;
        }

        return $user->hasRole('conductor')
            && $vehiculo->conductor?->user_id === $user->id;
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
    public function update(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }
}
