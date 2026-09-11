<?php

namespace App\Policies;

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
        return $user->hasAnyRole(['admin', 'visor']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Programacion $programacion): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Programacion $programacion): bool
    {
        return $user->hasRole('admin');
    }
}
