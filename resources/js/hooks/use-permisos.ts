import { usePage } from '@inertiajs/react';
import type { Permiso } from '@/types';

/**
 * Los permisos de la sesión: los del rol más los que se le dieron sueltos al
 * usuario, tal como los resuelve el backend.
 *
 * Cada pantalla pregunta por la acción concreta —`puede('viajes.anular')`— y
 * no por el rol, porque los roles ahora se arman desde el panel y un nombre
 * de rol ya no dice qué habilita.
 *
 * Esto es comodidad de la interfaz, no seguridad: quien autoriza de verdad
 * son las policies del backend.
 */
export function usePermisos() {
    const { auth } = usePage().props;

    return {
        puede: (permiso: Permiso): boolean => auth.permisos.includes(permiso),
        /** Usuarios y roles no se delegan: siguen siendo solo del admin. */
        esAdmin: auth.roles.includes('admin'),
    };
}
