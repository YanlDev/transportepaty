/**
 * Asigna un color estable a un cliente a partir de su nombre, para
 * distinguir de un vistazo la mezcla de clientes en una lista de viajes. No
 * hay catálogo de clientes detrás (llegan como texto libre de la GR), así
 * que el color por default sale de un hash del nombre en vez de un mapeo a
 * mano — mismo cliente, mismo color, siempre, sin mantenimiento.
 *
 * Excepción: los clientes de mayor volumen tienen su propio tratamiento a
 * pedido (ver `ESPECIALES`) en vez de dejarlos caer donde toque en la
 * paleta genérica por hash.
 *
 * La paleta evita azul-índigo/violeta a propósito: es el acento de marca de
 * la app (botones, links, foco), y un cliente con ese mismo tono se
 * confundiría con la UI.
 */
const PALETA = [
    'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 dark:bg-blue-950 dark:text-blue-300',
    'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300',
    'bg-teal-50 text-teal-700 ring-1 ring-teal-600/20 dark:bg-teal-950 dark:text-teal-300',
    'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-950 dark:text-rose-300',
    'bg-fuchsia-50 text-fuchsia-700 ring-1 ring-fuchsia-600/20 dark:bg-fuchsia-950 dark:text-fuchsia-300',
    'bg-orange-50 text-orange-700 ring-1 ring-orange-600/20 dark:bg-orange-950 dark:text-orange-300',
    'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-600/20 dark:bg-cyan-950 dark:text-cyan-300',
    'bg-lime-50 text-lime-700 ring-1 ring-lime-600/20 dark:bg-lime-950 dark:text-lime-300',
] as const;

const PUNTO_PALETA = [
    'bg-blue-500',
    'bg-amber-500',
    'bg-teal-500',
    'bg-rose-500',
    'bg-fuchsia-500',
    'bg-orange-500',
    'bg-cyan-500',
    'bg-lime-500',
] as const;

/**
 * `coincide` recibe el nombre del cliente ya en mayúsculas. Promart aparece
 * en algunas GR como «HOME CENTERS PERUANOS» (la razón social detrás de la
 * marca) en vez de «PROMART», así que matchea ambos.
 */
const ESPECIALES: {
    coincide: (cliente: string) => boolean;
    pill: string;
    punto: string;
}[] = [
    {
        coincide: (cliente) => cliente.includes('MINSUR'),
        pill: 'bg-blue-600 text-white dark:bg-blue-500',
        punto: 'bg-white',
    },
    {
        // «HOMECENTERS PERUANOS S.A.» es la razón social detrás de la marca
        // Promart, y llega así (sin espacio entre «Home» y «Centers») en
        // algunas GR — de ahí quitar espacios antes de comparar en vez de
        // buscar el literal «HOME CENTERS».
        coincide: (cliente) =>
            cliente.includes('PROMART') ||
            cliente.replace(/\s+/g, '').includes('HOMECENTERS'),
        pill: 'bg-orange-500 text-black dark:bg-orange-400',
        punto: 'bg-black',
    },
];

function hash(texto: string): number {
    let h = 0;

    for (let i = 0; i < texto.length; i++) {
        h = (h * 31 + texto.charCodeAt(i)) | 0;
    }

    return Math.abs(h);
}

/**
 * @returns clases del pill (fondo + texto) y del punto de color, listas para
 * usar en un `className`.
 */
export function clienteColor(cliente: string): { pill: string; punto: string } {
    const clienteEnMayusculas = cliente.toUpperCase();
    const especial = ESPECIALES.find(({ coincide }) =>
        coincide(clienteEnMayusculas),
    );

    if (especial) {
        return { pill: especial.pill, punto: especial.punto };
    }

    const indice = hash(cliente) % PALETA.length;

    return { pill: PALETA[indice], punto: PUNTO_PALETA[indice] };
}
