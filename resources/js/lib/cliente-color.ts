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

/** Mismos colores que `PUNTO_PALETA`, como variable CSS para recharts. */
const CHART_PALETA = [
    'var(--color-blue-500)',
    'var(--color-amber-500)',
    'var(--color-teal-500)',
    'var(--color-rose-500)',
    'var(--color-fuchsia-500)',
    'var(--color-orange-500)',
    'var(--color-cyan-500)',
    'var(--color-lime-500)',
] as const;

/**
 * `coincide` recibe el nombre del cliente ya en mayúsculas. Promart aparece
 * en algunas GR como «HOME CENTERS PERUANOS» (la razón social detrás de la
 * marca) en vez de «PROMART», así que matchea ambos.
 *
 * `chart` es el mismo color que `punto`/`pill`, pero como variable CSS de la
 * paleta de Tailwind (`--color-{nombre}-{tono}`, generada automáticamente
 * porque esa clase se usa arriba) — recharts pinta con `fill`, que no
 * entiende clases de Tailwind, así que necesita el valor crudo.
 *
 * Colores sacados del logo real de cada empresa (no inventados): Minsur y
 * Promart ya estaban así por decisión previa; Crisar, Porcelatino y San
 * Lorenzo se verificaron contra su logo público (2026-08-25) — las tres son
 * de la familia roja en la vida real, así que se separan con un tono
 * distinto cada una para que sigan siendo distinguibles en un gráfico.
 */
const ESPECIALES: {
    coincide: (cliente: string) => boolean;
    pill: string;
    punto: string;
    chart: string;
}[] = [
    {
        coincide: (cliente) => cliente.includes('MINSUR'),
        pill: 'bg-blue-600 text-white dark:bg-blue-500',
        punto: 'bg-white',
        chart: 'var(--color-blue-600)',
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
        chart: 'var(--color-orange-500)',
    },
    {
        // Detrás de Crisar está Ajeper (Kola Real / Big Cola): el remitente
        // real de la carga, aunque quien contrata a Paty y aparece como
        // cliente es Crisar (ver `ImportadorViaje::clienteReal()`).
        coincide: (cliente) => cliente.includes('CRISAR'),
        pill: 'bg-rose-600 text-white dark:bg-rose-500',
        punto: 'bg-white',
        chart: 'var(--color-rose-600)',
    },
    {
        // Llega como «PORCELANATO LATINO» (razón social) en la GR, aunque la
        // carpeta y el trato diario le dicen «Porcelatino».
        coincide: (cliente) =>
            cliente.includes('PORCELANATO') || cliente.includes('PORCELATINO'),
        pill: 'bg-red-600 text-white dark:bg-red-500',
        punto: 'bg-white',
        chart: 'var(--color-red-600)',
    },
    {
        coincide: (cliente) => cliente.includes('SAN LORENZO'),
        pill: 'bg-amber-800 text-white dark:bg-amber-700',
        punto: 'bg-white',
        chart: 'var(--color-amber-800)',
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
 * usar en un `className`, más `chart` (el mismo color en `var(--color-*)`)
 * para pintar barras o sectores de un gráfico con recharts.
 */
export function clienteColor(cliente: string): {
    pill: string;
    punto: string;
    chart: string;
} {
    const clienteEnMayusculas = cliente.toUpperCase();
    const especial = ESPECIALES.find(({ coincide }) =>
        coincide(clienteEnMayusculas),
    );

    if (especial) {
        return {
            pill: especial.pill,
            punto: especial.punto,
            chart: especial.chart,
        };
    }

    const indice = hash(cliente) % PALETA.length;

    return {
        pill: PALETA[indice],
        punto: PUNTO_PALETA[indice],
        chart: CHART_PALETA[indice],
    };
}
