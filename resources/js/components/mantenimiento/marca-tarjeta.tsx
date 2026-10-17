import { Link } from '@inertiajs/react';
import {
    MarcaLogo,
    resumenNiveles,
} from '@/components/mantenimiento/marca-logo';
import { cn } from '@/lib/utils';
import type {
    EstadoMantenimiento,
    MarcaMantenimiento,
    UnidadMantenimiento,
} from '@/types/mantenimiento';

export type ConteoEstados = Record<EstadoMantenimiento, number>;

/** El color del número de cada estado del semáforo. */
export const colorEstado: Record<EstadoMantenimiento, string> = {
    vencido: 'text-red-700 dark:text-red-400',
    proximo: 'text-amber-700 dark:text-amber-400',
    al_dia: 'text-emerald-700 dark:text-emerald-400',
    sin_datos: 'text-muted-foreground',
};

const barraEstado: Record<EstadoMantenimiento, string> = {
    vencido: 'bg-red-500',
    proximo: 'bg-amber-500',
    al_dia: 'bg-emerald-500',
    sin_datos: 'bg-zinc-300 dark:bg-zinc-600',
};

const ordenEstados: EstadoMantenimiento[] = [
    'vencido',
    'proximo',
    'al_dia',
    'sin_datos',
];

export function contarPorEstado(
    unidades: UnidadMantenimiento[],
): ConteoEstados {
    const conteo = { vencido: 0, proximo: 0, al_dia: 0, sin_datos: 0 };

    for (const unidad of unidades) {
        conteo[unidad.estado]++;
    }

    return conteo;
}

/**
 * Una marca de la flota: su logo, cuántas unidades tiene y cómo están, en
 * una barra con los colores del semáforo.
 */
export function TarjetaMarca({
    marca,
    conteo,
    activa = false,
    onClick,
    href,
}: {
    marca: MarcaMantenimiento;
    conteo: ConteoEstados;
    activa?: boolean;
    /** Como filtro: tocarla la marca o la desmarca. */
    onClick?: () => void;
    /** Como acceso: lleva a otra pantalla. */
    href?: string;
}) {
    const total = ordenEstados.reduce(
        (suma, estado) => suma + conteo[estado],
        0,
    );
    const clases = cn(
        'flex flex-col gap-2.5 rounded-xl border bg-card p-3 text-left transition-colors hover:bg-accent/50 sm:gap-3 sm:p-4',
        activa && 'ring-2 ring-primary ring-offset-1 ring-offset-background',
    );

    const contenido = (
        <>
            <div className="flex items-center gap-2 sm:gap-3">
                <MarcaLogo
                    marca={marca.marca}
                    logoUrl={marca.logo_url}
                    className="max-sm:h-9 max-sm:w-12"
                />
                <div className="flex min-w-0 flex-col">
                    <span className="truncate text-sm font-semibold sm:text-base">
                        {marca.marca}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {total} {total === 1 ? 'unidad' : 'unidades'}
                    </span>
                </div>
                {conteo.vencido > 0 && (
                    <span className="ml-auto rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 tabular-nums max-sm:hidden dark:bg-red-950/60 dark:text-red-400">
                        {conteo.vencido}{' '}
                        {conteo.vencido === 1 ? 'vencida' : 'vencidas'}
                    </span>
                )}
            </div>

            <div className="flex h-2 overflow-hidden rounded-full bg-muted">
                {total > 0 &&
                    ordenEstados.map(
                        (estado) =>
                            conteo[estado] > 0 && (
                                <span
                                    key={estado}
                                    className={barraEstado[estado]}
                                    style={{
                                        width: `${(conteo[estado] / total) * 100}%`,
                                    }}
                                />
                            ),
                    )}
            </div>

            <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs tabular-nums">
                {conteo.vencido > 0 && (
                    <span className={cn(colorEstado.vencido, 'sm:hidden')}>
                        {conteo.vencido}{' '}
                        {conteo.vencido === 1 ? 'vencida' : 'vencidas'}
                    </span>
                )}
                <span className={colorEstado.proximo}>
                    {conteo.proximo}{' '}
                    {conteo.proximo === 1 ? 'próxima' : 'próximas'}
                </span>
                <span className={colorEstado.al_dia}>
                    {conteo.al_dia} al día
                </span>
                {conteo.sin_datos > 0 && (
                    <span
                        className={cn(colorEstado.sin_datos, 'max-sm:hidden')}
                    >
                        {conteo.sin_datos} sin datos
                    </span>
                )}
            </div>

            <span className="text-xs text-muted-foreground tabular-nums max-sm:hidden">
                {resumenNiveles(marca.niveles)}
            </span>
        </>
    );

    if (href !== undefined) {
        return (
            <Link href={href} className={clases}>
                {contenido}
            </Link>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activa}
            className={clases}
        >
            {contenido}
        </button>
    );
}
