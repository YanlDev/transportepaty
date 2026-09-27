<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Conductor;
use App\Models\User;

class ConductorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ConductoresVer);
    }

    public function view(User $user, Conductor $conductor): bool
    {
        return $user->checkPermissionTo(Permiso::ConductoresVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ConductoresCrear);
    }

    public function update(User $user, Conductor $conductor): bool
    {
        return $user->checkPermissionTo(Permiso::ConductoresEditar);
    }

    public function delete(User $user, Conductor $conductor): bool
    {
        return $user->checkPermissionTo(Permiso::ConductoresEliminar);
    }
}
