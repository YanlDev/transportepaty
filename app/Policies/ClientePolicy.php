<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Cliente;
use App\Models\User;

/**
 * Mismo criterio que el padrón de conductores: el visor consulta, el admin
 * mantiene.
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ClientesVer);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->checkPermissionTo(Permiso::ClientesVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ClientesCrear);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->checkPermissionTo(Permiso::ClientesEditar);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->checkPermissionTo(Permiso::ClientesEliminar);
    }
}
