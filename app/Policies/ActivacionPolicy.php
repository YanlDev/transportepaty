<?php

namespace App\Policies;

use App\Models\Activacion;
use App\Models\User;
use App\Models\Vehiculo;

class ActivacionPolicy
{
    /**
     * Whether the user can see the activation history of the given vehicle.
     *
     * Admins and viewers see every vehicle; a driver only sees the vehicle
     * assigned to them.
     */
    public function verHistorial(User $user, Vehiculo $vehiculo): bool
    {
        if ($user->hasAnyRole(['admin', 'visor'])) {
            return true;
        }

        return $user->hasRole('conductor')
            && $vehiculo->conductor?->user_id === $user->id;
    }

    /**
     * Whether the user can register an activation for the vehicle.
     *
     * Admins for any vehicle; a driver only for the vehicle assigned to them.
     */
    public function registrar(User $user, Vehiculo $vehiculo): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('conductor')
            && $vehiculo->conductor?->user_id === $user->id;
    }

    /**
     * Whether the user can delete the activation record. Only admins.
     */
    public function delete(User $user, Activacion $activacion): bool
    {
        return $user->hasRole('admin');
    }
}
