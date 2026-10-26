/**
 * Los colores de las tarjetas de la pizarra de abastecimiento, para que la
 * pantalla se lea igual que el papel de la oficina.
 */
const COLORES: Record<string, { borde: string; cabecera: string }> = {
    rosa: {
        borde: 'border-pink-300 dark:border-pink-900',
        cabecera:
            'bg-pink-100 text-pink-900 dark:bg-pink-950 dark:text-pink-200',
    },
    verde: {
        borde: 'border-emerald-300 dark:border-emerald-900',
        cabecera:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
    morado: {
        borde: 'border-violet-300 dark:border-violet-900',
        cabecera:
            'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200',
    },
    naranja: {
        borde: 'border-orange-300 dark:border-orange-900',
        cabecera:
            'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200',
    },
    azul: {
        borde: 'border-sky-300 dark:border-sky-900',
        cabecera: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    },
    turquesa: {
        borde: 'border-teal-300 dark:border-teal-900',
        cabecera:
            'bg-teal-100 text-teal-900 dark:bg-teal-950 dark:text-teal-200',
    },
    gris: {
        borde: 'border-zinc-300 dark:border-zinc-700',
        cabecera:
            'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
    },
};

export const COLORES_TARJETA = Object.keys(COLORES);

export function colorTarjeta(color: string | null): {
    borde: string;
    cabecera: string;
} {
    return COLORES[color ?? 'gris'] ?? COLORES.gris;
}
