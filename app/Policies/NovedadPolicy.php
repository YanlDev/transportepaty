<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Novedad;
use App\Models\User;

class NovedadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::VehiculosVer);
    }

    public function view(User $user, Novedad $novedad): bool
    {
        return $user->checkPermissionTo(Permiso::VehiculosVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::NovedadesRegistrar);
    }

    public function update(User $user, Novedad $novedad): bool
    {
        return $user->checkPermissionTo(Permiso::NovedadesLevantar);
    }

    public function delete(User $user, Novedad $novedad): bool
    {
        return $user->checkPermissionTo(Permiso::NovedadesLevantar);
    }
}
