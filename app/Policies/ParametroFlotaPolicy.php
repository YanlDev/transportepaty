<?php

namespace App\Policies;

use App\Models\User;

/**
 * La estructura de costos de la casa: lo que alimenta toda cotización.
 *
 * Tiene policy propia y no se apoya en la de cotizaciones —que es donde estaba
 * colgada— porque son dos permisos distintos: cotizar es ponerle precio a un
 * viaje con las tasas vigentes, y esto es cambiar esas tasas para todos los
 * viajes que vengan. Compartir el chequeo hacía que tocar `CotizacionPolicy`
 * moviera sin aviso quién puede reescribir los costos.
 *
 * Por ahora coinciden en el admin; lo que importa es que puedan dejar de
 * hacerlo sin que uno arrastre al otro.
 */
class ParametroFlotaPolicy
{
    /**
     * La pantalla es el editor mismo —no hay una vista de solo lectura de los
     * costos—, así que verla y cambiarlos es el mismo permiso.
     */
    public function update(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
