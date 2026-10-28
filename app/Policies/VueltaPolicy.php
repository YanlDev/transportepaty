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

    /**
     * El candado: cerrado, el ticket ya no recibe combustible ni viáticos.
     * Lo cierra quien ve toda la operación, cuando el viaje terminó de
     * verdad (no al llegar la GR de regreso: esa se emite al cargar).
     */
    public function cerrar(User $user, Vuelta $vuelta): bool
    {
        return in_array($vuelta->estado, [EstadoVuelta::Programada, EstadoVuelta::EnCurso], true)
            && $user->checkPermissionTo(Permiso::ProgramacionCerrar);
    }

    /**
     * Solo el último ticket de la unidad se reabre: las GR nuevas caen en el
     * abierto más reciente, y reabrir uno viejo las mandaría al pasado.
     */
    public function reabrir(User $user, Vuelta $vuelta): bool
    {
        return $vuelta->estado === EstadoVuelta::Cerrada
            && ! Vuelta::query()
                ->where('tracto_id', $vuelta->tracto_id)
                ->whereKeyNot($vuelta->id)
                ->where('estado', '!=', EstadoVuelta::Anulada)
                ->where('inicio', '>', $vuelta->inicio)
                ->exists()
            && $user->checkPermissionTo(Permiso::ProgramacionCerrar);
    }
}
