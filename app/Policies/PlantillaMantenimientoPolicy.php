<?php

namespace App\Policies;

use App\Models\PlantillaMantenimiento;
use App\Models\User;

class PlantillaMantenimientoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PlantillaMantenimiento $plantillaMantenimiento): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, PlantillaMantenimiento $plantillaMantenimiento): bool
    {
        return $user->hasRole('admin');
    }
}
