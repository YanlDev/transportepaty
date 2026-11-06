<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Abastecimiento;
use App\Models\User;

/**
 * El área de abastecimiento: ver el tablero, registrar el combustible que se
 * autoriza y configurar los puntos de carga son tres permisos aparte,
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
     * Nada se borra: se anula con motivo. Lo de un ticket cerrado (o anulado) no
     * se toca: el cerrado se reabre antes.
     */
    public function anular(User $user, Abastecimiento $abastecimiento): bool
    {
        return ! $abastecimiento->estaAnulado()
            && ($abastecimiento->vuelta?->estaAbierta() ?? true)
            && $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
    }

    /**
     * Lo que de verdad cargó, según el voucher del grifo. Se puede corregir
     * mientras el ticket siga abierto.
     */
    public function registrarDespacho(User $user, Abastecimiento $abastecimiento): bool
    {
        return ! $abastecimiento->estaAnulado()
            && ($abastecimiento->vuelta?->estaAbierta() ?? true)
            && $user->checkPermissionTo(Permiso::AbastecimientoRegistrar);
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
