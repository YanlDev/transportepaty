<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Cobro;
use App\Models\User;

/**
 * Los cobros los registra quien factura y cobra. Anular uno saca plata de la
 * cobranza: va con el mismo permiso que anular una factura.
 */
class CobroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::CobranzaVer);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permiso::CobranzaGestionar);
    }

    public function aplicar(User $user, Cobro $cobro): bool
    {
        return ! $cobro->estaAnulado() && $user->checkPermissionTo(Permiso::CobranzaGestionar);
    }

    public function anular(User $user, Cobro $cobro): bool
    {
        return ! $cobro->estaAnulado() && $user->checkPermissionTo(Permiso::CobranzaAnular);
    }
}
