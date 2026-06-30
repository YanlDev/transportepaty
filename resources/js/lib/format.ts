const LOCALE = 'es-PE';

/**
 * Formats a `Y-m-d` date string as `dd/mm/yyyy`. Returns `—` when empty.
 */
export function formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
        return '—';
    }

    return new Date(`${fecha}T00:00:00`).toLocaleDateString(LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Formats a number as Peruvian soles (e.g. `S/ 160.00`). Returns `—` when null.
 */
export function formatearSoles(monto: number | null | undefined): string {
    if (monto === null || monto === undefined) {
        return '—';
    }

    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency: 'PEN',
    }).format(monto);
}

/**
 * Formats an ISO datetime string as `dd/mm/yyyy hh:mm`.
 */
export function formatearFechaHora(fecha: string): string {
    return new Date(fecha).toLocaleString(LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
