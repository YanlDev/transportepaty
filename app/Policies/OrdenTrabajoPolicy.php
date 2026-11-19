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
     * Crear una pre-orden: el mecánico elegido arma el requerimiento.
     */
    public function solicitar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoSolicitar);
    }

    /**
     * Validar lo que el mecánico terminó: se finaliza desde la orden o se le
     * devuelve con su observación.
     */
    public function observar(User $user, OrdenTrabajo $orden): bool
    {
        return $orden->estado === EstadoOrdenTrabajo::PorRevisar && $this->update($user, $orden);
    }

    /**
     * Una pre-orden que no se va a atender se rechaza con su motivo.
     */
    public function rechazar(User $user, OrdenTrabajo $orden): bool
    {
        return $orden->estado->sinAprobar() && $this->update($user, $orden);
    }

    /**
     * Aprobar (o devolver) el requerimiento que envió el mecánico.
     */
    public function aprobarRequerimiento(User $user, OrdenTrabajo $orden): bool
    {
        return $orden->estado === EstadoOrdenTrabajo::Requerimiento && $this->update($user, $orden);
    }

    /**
     * La pantalla del mecánico: sus órdenes asignadas.
     */
    public function verAsignadas(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoEjecutar);
    }

    /**
     * El mecánico solo abre y trabaja las órdenes que le asignaron, desde la
     * pre-orden.
     */
    public function ejecutar(User $user, OrdenTrabajo $orden): bool
    {
        return ! $orden->estaAnulado()
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
