<?php

namespace App\Policies;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\Permiso;
use App\Models\OrdenTrabajo;
use App\Models\User;

class OrdenTrabajoPolicy
{
    /**
     * Ver el semáforo de la flota, la ficha de cada unidad y sus órdenes.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoGestionar);
    }

    public function update(User $user, OrdenTrabajo $orden): bool
    {
        return ! $orden->estaAnulado() && $user->checkPermissionTo(Permiso::MantenimientoGestionar);
    }

    /**
     * Pedirle un trabajo a taller: queda como solicitud hasta que taller la
     * acepta.
     */
    public function solicitar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoSolicitar);
    }

    /**
     * Taller revisa lo que el mecánico terminó: lo aprueba desde la orden o
     * lo devuelve con su observación.
     */
    public function observar(User $user, OrdenTrabajo $orden): bool
    {
        return $orden->estado === EstadoOrdenTrabajo::PorRevisar && $this->update($user, $orden);
    }

    /**
     * Una solicitud que taller no va a atender se rechaza con su motivo.
     */
    public function rechazar(User $user, OrdenTrabajo $orden): bool
    {
        return $orden->estado === EstadoOrdenTrabajo::Solicitada && $this->update($user, $orden);
    }

    /**
     * La pantalla del mecánico: sus órdenes asignadas.
     */
    public function verAsignadas(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoEjecutar);
    }

    /**
     * El mecánico solo abre y trabaja las órdenes que taller le asignó.
     */
    public function ejecutar(User $user, OrdenTrabajo $orden): bool
    {
        return ! $orden->estaAnulado()
            && $orden->estado !== EstadoOrdenTrabajo::Solicitada
            && $user->checkPermissionTo(Permiso::MantenimientoEjecutar)
            && $orden->esDeMecanico($user);
    }

    /**
     * Una orden no se borra: se anula, y eso deja un hueco en el plan de la
     * unidad, así que tiene su propio permiso.
     */
    public function anular(User $user, OrdenTrabajo $orden): bool
    {
        return ! $orden->estaAnulado() && $user->checkPermissionTo(Permiso::MantenimientoAnular);
    }
}
