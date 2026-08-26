const LOCALE = 'es-PE';

/**
 * Formats a `Y-m-d` date string as `dd/mm/yyyy`. Returns `—` when empty.
 */
export function formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
        return '—';
    }

    // Acepta tanto "Y-m-d" como ISO con hora. Las fechas puras se anclan a
    // medianoche local para evitar corrimientos por zona horaria; cualquier
    // valor no parseable devuelve "—" en vez de "Invalid Date".
    const d = new Date(fecha.length === 10 ? `${fecha}T00:00:00` : fecha);

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return d.toLocaleDateString(LOCALE, {
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

/**
 * Placa sin separadores, como la pide la operación: `BJF-934` → `BJF934`.
 */
export function formatearPlaca(placa: string): string {
    return placa.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
}

/**
 * El peso de la GR viene en KGM o TNE (los dos únicos códigos SUNAT que se
 * usan acá). Para listados se muestra siempre en toneladas —un tracto carga
 * como máximo ~30 TNE, así que en KGM son 5-6 dígitos que no caben bien en
 * una columna angosta—, sin tocar el dato original, que sigue guardado tal
 * cual vino en el PDF.
 */
export function formatearPeso(peso: number, unidadPeso: string): string {
    const toneladas = unidadPeso === 'KGM' ? peso / 1000 : peso;

    return `${toneladas.toLocaleString(LOCALE, { maximumFractionDigits: 2 })} TNE`;
}
