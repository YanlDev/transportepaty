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
     * Corregir lo autorizado (punto, galones, fecha, tramo) es de quien
     * corrige registros: quien registra no enmienda lo suyo.
     */
    public function update(User $user, Abastecimiento $abastecimiento): bool
    {
        return $this->sePuedeTocar($abastecimiento)
            && $user->checkPermissionTo(Permiso::AbastecimientoCorregir);
    }

    /**
     * Nada se borra: se anula con motivo, y solo quien corrige registros.
     */
    public function anular(User $user, Abastecimiento $abastecimiento): bool
    {
        return $this->sePuedeTocar($abastecimiento)
            && $user->checkPermissionTo(Permiso::AbastecimientoCorregir);
    }

    /**
     * Lo que de verdad cargó, según el voucher del grifo. Quien registra lo
     * anota una vez; corregirlo después es de quien corrige registros.
     */
    public function registrarDespacho(User $user, Abastecimiento $abastecimiento): bool
    {
        if (! $this->sePuedeTocar($abastecimiento)) {
            return false;
        }

        return $user->checkPermissionTo(Permiso::AbastecimientoCorregir)
            || (! $abastecimiento->tieneDespacho() && $user->checkPermissionTo(Permiso::AbastecimientoRegistrar));
    }

    /**
     * El precio del voucher sí lo corrige quien registra: es lo que más se
     * equivoca y no mueve galones ni kilometraje.
     */
    public function corregirPrecio(User $user, Abastecimiento $abastecimiento): bool
    {
        return $this->sePuedeTocar($abastecimiento)
            && $abastecimiento->tieneDespacho()
            && ($user->checkPermissionTo(Permiso::AbastecimientoRegistrar)
                || $user->checkPermissionTo(Permiso::AbastecimientoCorregir));
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

    /**
     * Lo anulado ya no se toca, y lo de un ticket cerrado (o anulado) tampoco:
     * el cerrado se reabre antes.
     */
    private function sePuedeTocar(Abastecimiento $abastecimiento): bool
    {
        return ! $abastecimiento->estaAnulado()
            && ($abastecimiento->vuelta?->estaAbierta() ?? true);
    }
}
