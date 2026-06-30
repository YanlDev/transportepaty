<?php

namespace App\Policies;

use App\Models\CargaCombustible;
use App\Models\User;
use App\Models\Vehiculo;

class CargaCombustiblePolicy
{
    /**
     * Whether the user can see the fuel history of the given vehicle.
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
     * Whether the user can register a load (upload photos) for the vehicle.
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
     * Whether the user can fill/edit the load's data. Only admins process loads.
     */
    public function update(User $user, CargaCombustible $cargaCombustible): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Whether the user can delete the load. Only admins.
     */
    public function delete(User $user, CargaCombustible $cargaCombustible): bool
    {
        return $user->hasRole('admin');
    }
}
