<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;

class LlantaPolicy
{
    /**
     * Ver las llantas de la flota: va con el resto de mantenimiento.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoVer);
    }

    /**
     * Instalar, medir, rotar, reparar o retirar: lo hace el llantero.
     */
    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoLlantas);
    }

    /**
     * Corregir lo anotado: los datos de una llanta o un movimiento.
     */
    public function update(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoLlantas);
    }

    /**
     * Ver las llantas de una unidad: quien ve mantenimiento, y el mecánico
     * que tiene asignada una orden abierta de esa unidad.
     */
    public function verUnidad(User $user, Vehiculo $vehiculo): bool
    {
        return $this->viewAny($user) || $this->ordenDelMecanico($user, $vehiculo) !== null;
    }

    /**
     * Montar, rotar, reparar, retirar o medir las llantas de una unidad:
     * quien registra llantas, y el mecánico mientras ejecuta una orden de
     * esa unidad (ya dejó la foto del antes y dejará la del después).
     */
    public function registrarEnUnidad(User $user, Vehiculo $vehiculo): bool
    {
        return $this->create($user) || ($this->ordenDelMecanico($user, $vehiculo)?->estado->enEjecucion() ?? false);
    }

    /**
     * La orden abierta de la unidad que ese mecánico tiene asignada.
     */
    public function ordenDelMecanico(User $user, Vehiculo $vehiculo): ?OrdenTrabajo
    {
        if (! $user->checkPermissionTo(Permiso::MantenimientoEjecutar)) {
            return null;
        }

        return OrdenTrabajo::query()
            ->enTaller()
            ->where('vehiculo_id', $vehiculo->id)
            ->orderByDesc('id')
            ->get()
            ->first(fn (OrdenTrabajo $orden): bool => $orden->esDeMecanico($user));
    }
}
