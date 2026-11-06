<?php

namespace App\Policies;

use App\Enums\EstadoVuelta;
use App\Enums\Permiso;
use App\Models\User;
use App\Models\Vuelta;

/**
 * El OK de salida: ver la bandeja de taller y dar el apto son permisos
 * aparte, porque abastecimiento necesita saber qué está apto sin poder
 * firmarlo.
 */
class RevisionTallerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::TallerVer);
    }

    /**
     * Solo un ticket abierto se revisa: el cerrado ya volvió.
     */
    public function create(User $user, Vuelta $vuelta): bool
    {
        return in_array($vuelta->estado, [EstadoVuelta::Programada, EstadoVuelta::EnCurso], true)
            && $user->checkPermissionTo(Permiso::TallerRevisar);
    }
}
