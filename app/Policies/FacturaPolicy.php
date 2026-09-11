<?php

namespace App\Policies;

use App\Models\Factura;
use App\Models\User;

/**
 * La cobranza la maneja el contador; el admin conserva acceso porque es quien
 * responde por el sistema. El visor queda fuera a propósito: ve la operación,
 * no los montos.
 */
class FacturaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function view(User $user, Factura $factura): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function update(User $user, Factura $factura): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }

    public function delete(User $user, Factura $factura): bool
    {
        return $user->hasAnyRole(['admin', 'contador']);
    }
}
