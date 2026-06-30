<?php

namespace App\Policies;

use App\Models\Mantenimiento;
use App\Models\User;
use App\Models\Vehiculo;

class MantenimientoPolicy
{
    public function verHistorial(User $user, Vehiculo $vehiculo): bool
    {
        if ($user->hasAnyRole(['admin', 'visor'])) {
            return true;
        }

        return $user->hasRole('conductor')
            && $vehiculo->conductor?->user_id === $user->id;
    }

    public function registrar(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Mantenimiento $mantenimiento): bool
    {
        return $this->verHistorial($user, $mantenimiento->vehiculo);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->hasRole('admin');
    }
}
