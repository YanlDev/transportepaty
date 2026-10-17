<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\PlanMantenimiento;
use App\Models\User;

class PlanMantenimientoPolicy
{
    /**
     * Cambiar los km, los meses o lo que lleva cada nivel recalcula el
     * semáforo de toda la marca: por eso va en un permiso aparte del taller.
     */
    public function update(User $user, PlanMantenimiento $plan): bool
    {
        return $user->checkPermissionTo(Permiso::MantenimientoConfigurar);
    }
}
