<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehiculo;

class VehiculoPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * El contador entra en lectura: al facturar tiene que identificar la placa
     * que viene en la guía. El menú ya le mostraba Tractos y Carretas, así que
     * hasta acá la policy le devolvía un 403 sobre un enlace visible.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'visor', 'contador']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * Las unidades no se asignan a un conductor, así que el padrón es visible
     * para cualquier usuario con acceso al sistema.
     */
    public function view(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasAnyRole(['admin', 'visor', 'contador']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Vehiculo $vehiculo): bool
    {
        return $user->hasRole('admin');
    }
}
