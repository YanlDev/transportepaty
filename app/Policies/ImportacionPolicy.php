<?php

namespace App\Policies;

use App\Models\Importacion;
use App\Models\User;

class ImportacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Importacion $importacion): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Importacion $importacion): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Importacion $importacion): bool
    {
        return $user->hasRole('admin');
    }
}
