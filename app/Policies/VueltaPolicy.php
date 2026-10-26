<?php

namespace App\Policies;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\Permiso;
use App\Models\User;
use App\Models\Vuelta;

/**
 * Las vueltas son la programación vista de punta a punta: las lee quien ve
 * la programación y las arma quien programa. Las del historial salen de las
 * GR y no se editan a mano.
 */
class VueltaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionCrear);
    }

    /**
     * Se corrige mientras está programada o andando: una cerrada ya es
     * historia.
     */
    public function update(User $user, Vuelta $vuelta): bool
    {
        return $vuelta->origen === OrigenVuelta::Programacion
            && in_array($vuelta->estado, [EstadoVuelta::Programada, EstadoVuelta::EnCurso], true)
            && $user->checkPermissionTo(Permiso::ProgramacionEditar);
    }

    /**
     * Mandar los avisos habla en nombre de la empresa por WhatsApp, así que
     * no va atado a poder editar la vuelta.
     */
    public function avisar(User $user, Vuelta $vuelta): bool
    {
        return $user->checkPermissionTo(Permiso::ProgramacionAvisar);
    }

    public function delete(User $user, Vuelta $vuelta): bool
    {
        return $vuelta->origen === OrigenVuelta::Programacion
            && $user->checkPermissionTo(Permiso::ProgramacionEliminar);
    }
}
