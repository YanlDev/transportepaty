<?php

namespace App\Policies;

use App\Enums\EstadoVuelta;
use App\Enums\Permiso;
use App\Models\Abastecimiento;
use App\Models\User;

/**
 * El área de abastecimiento: ver el tablero, registrar el combustible que se
 * autoriza y configurar la pizarra de dotaciones son tres permisos aparte,
 * porque la pizarra la decide quien manda en el área, no quien registra.
 */
class AbastecimientoPolicy
{
    /**
     * Facturación entra a corregir viáticos aunque no vea el resto del área.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoVer)
            || $user->checkPermissionTo(Permiso::AbastecimientoViaticos);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    /**
     * Nada se borra: se anula con motivo. Lo de un ticket cerrado no se toca:
     * se reabre antes.
     */
    public function anular(User $user, Abastecimiento $abastecimiento): bool
    {
        return ! $abastecimiento->estaAnulado()
            && $abastecimiento->vuelta?->estado !== EstadoVuelta::Cerrada
            && $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    /**
     * Lo que de verdad cargó, según el voucher del grifo. Se puede corregir
     * mientras el ticket no esté cerrado.
     */
    public function registrarDespacho(User $user, Abastecimiento $abastecimiento): bool
    {
        return ! $abastecimiento->estaAnulado()
            && $abastecimiento->vuelta?->estado !== EstadoVuelta::Cerrada
            && $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    /**
     * Confirmar la tarjeta de la pizarra de un tramo: es lo que se autoriza.
     */
    public function confirmarDotacion(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    /**
     * Pasar el tope de la tarjeta es una excepción: la autoriza quien manda.
     */
    public function excederTope(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoExcederTope);
    }

    public function configurar(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::AbastecimientoConfigurar);
    }
}
