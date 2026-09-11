<?php

namespace App\Policies;

use App\Models\CuentaBancaria;
use App\Models\User;

/**
 * Mismo alcance que la cobranza: quien registra por dónde entró la plata es
 * quien administra la lista de cuentas.
 */
class CuentaBancariaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function view(User $user, CuentaBancaria $cuenta): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function update(User $user, CuentaBancaria $cuenta): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    /**
     * Una cuenta con facturas encima no se borra: dejaría sin rastro por dónde
     * se cobró. En ese caso se desactiva, que es lo que ofrece la interfaz.
     */
    public function delete(User $user, CuentaBancaria $cuenta): bool
    {
        return $user->hasAnyRole(['admin', 'contador'])
            && ! $cuenta->facturas()->exists();
    }
}
