<?php

namespace App\Policies;

use App\Enums\EstadoValeCompra;
use App\Enums\Permiso;
use App\Enums\TipoValeCompra;
use App\Models\User;
use App\Models\ValeCompra;

class ValeCompraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ComprasVer);
    }

    public function view(User $user, ValeCompra $vale): bool
    {
        return $user->checkPermissionTo(Permiso::ComprasVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::ComprasSolicitar);
    }

    /**
     * Corregir un vale lo devuelve a aprobación. Ya usado no se corrige.
     */
    public function update(User $user, ValeCompra $vale): bool
    {
        return ! $vale->estaAnulado()
            && $vale->recibido() <= 0
            && $user->checkPermissionTo(Permiso::ComprasSolicitar);
    }

    /**
     * Las compras las aprueba gerencia; las de emergencia en ruta, también
     * el jefe de operaciones. Sirve igual para rechazar.
     */
    public function aprobar(User $user, ValeCompra $vale): bool
    {
        if ($vale->estaAnulado() || $vale->estado !== EstadoValeCompra::Solicitado) {
            return false;
        }

        return $user->checkPermissionTo(Permiso::ComprasAprobar)
            || ($vale->tipo === TipoValeCompra::Emergencia && $user->checkPermissionTo(Permiso::ComprasAprobarEmergencia));
    }

    /**
     * Las entregas parciales no deberían pasar: solo las ve y las activa
     * quien tiene su permiso.
     */
    public function parciales(User $user, ValeCompra $vale): bool
    {
        return ! $vale->estaAnulado()
            && $vale->estado === EstadoValeCompra::Aprobado
            && $user->checkPermissionTo(Permiso::ComprasParciales);
    }

    public function confirmarServicio(User $user, ValeCompra $vale): bool
    {
        return ! $vale->estaAnulado()
            && $vale->estado === EstadoValeCompra::Aprobado
            && $user->checkPermissionTo(Permiso::ComprasSolicitar);
    }

    /**
     * Con compras ya ingresadas no se anula: primero se anulan los ingresos.
     */
    public function anular(User $user, ValeCompra $vale): bool
    {
        return ! $vale->estaAnulado()
            && $vale->recibido() <= 0
            && $user->checkPermissionTo(Permiso::ComprasAnular);
    }
}
