<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Programacion;
use App\Models\User;

/**
 * Abastecimiento necesita *ver* la programación del día para preparar la
 * carga, pero no arma el plan: eso lo decide operaciones. Por eso el visor
 * entra en modo lectura y solo el admin agrega, corrige o quita.
 */
class ProgramacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionCrear);
    }

    public function update(User $user, Programacion $programacion): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionEditar);
    }

    public function delete(User $user, Programacion $programacion): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionEliminar);
    }

    /**
     * Mandar el aviso de salida habla en nombre de la empresa por WhatsApp,
     * así que no va atado a poder editar la salida.
     */
    public function avisar(User $user, Programacion $programacion): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionAvisar);
    }
}
