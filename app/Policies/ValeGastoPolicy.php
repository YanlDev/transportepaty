<?php

namespace App\Policies;

use App\Enums\EstadoValeCompra;
use App\Enums\Permiso;
use App\Models\User;
use App\Models\ValeGasto;

class ValeGastoPolicy
{
    /**
     * Entra quien ve todos los vales y quien pide: este último solo ve los
     * suyos (lo filtra el listado).
     */
    public function viewAny(User $user): bool
    {
        return $this->veTodos($user) || $user->checkPermissionTo(Permiso::GastosSolicitar);
    }

    public function view(User $user, ValeGasto $vale): bool
    {
        return $this->veTodos($user)
            || ($vale->solicitado_por === $user->id && $user->checkPermissionTo(Permiso::GastosSolicitar));
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::GastosSolicitar);
    }

    /**
     * Todos los vales los aprueba gerencia. Sirve igual para rechazar.
     */
    public function aprobar(User $user, ValeGasto $vale): bool
    {
        return ! $vale->estaAnulado()
            && $vale->estado === EstadoValeCompra::Solicitado
            && $user->checkPermissionTo(Permiso::GastosAprobar);
    }

    /**
     * Quien pidió puede retirar su vale mientras nadie lo resolvió; ya
     * aprobado, solo lo anula quien tiene el permiso.
     */
    public function anular(User $user, ValeGasto $vale): bool
    {
        if ($vale->estaAnulado() || $vale->estado === EstadoValeCompra::Rechazado) {
            return false;
        }

        return $user->checkPermissionTo(Permiso::GastosAnular)
            || ($vale->estado === EstadoValeCompra::Solicitado
                && $vale->solicitado_por === $user->id
                && $user->checkPermissionTo(Permiso::GastosSolicitar));
    }

    /**
     * Quien ve los vales de todas las áreas, no solo los propios.
     */
    public function veTodos(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::GastosVer) || $user->checkPermissionTo(Permiso::GastosAprobar);
    }
}
