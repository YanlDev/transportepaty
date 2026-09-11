import { usePage } from '@inertiajs/react';

/**
 * Los permisos de la sesión, derivados de los roles que manda el backend.
 *
 * Están separados a propósito en «ver» y «editar»: el visor entra a los
 * módulos de operación pero no toca nada, así que un solo `puedeGestionar`
 * significaba cosas distintas en el menú y en las páginas. Cada permiso de
 * acá dice qué habilita, no quién es.
 *
 * Esto es comodidad de la interfaz, no seguridad: quien autoriza de verdad
 * son las policies del backend.
 */
export function usePermisos() {
    const { auth } = usePage().props;

    const esAdmin = auth.roles.includes('admin');
    const esVisor = auth.roles.includes('visor');
    const esContador = auth.roles.includes('contador');

    return {
        esAdmin,
        esVisor,
        esContador,
        /** Alta, edición y baja de cualquier entidad de la flota. */
        puedeEditar: esAdmin,
        /** Entrar a los módulos de operación, aunque sea de solo lectura. */
        puedeVerOperacion: esAdmin || esVisor,
        /** Emitir facturas y marcar cobranza. */
        puedeFacturar: esAdmin || esContador,
    };
}
