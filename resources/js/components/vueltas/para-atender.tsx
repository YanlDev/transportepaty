import { Link } from '@inertiajs/react';
import { ChevronDown, CircleAlert, X } from 'lucide-react';
import { useState } from 'react';
import guiasRutas from '@/actions/App/Http/Controllers/GuiaPorAsignarController';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TarjetaVuelta } from '@/types/vueltas';

/** Un grupo de tickets que esperan lo mismo, y el área que lo hace. */
type Grupo = {
    clave: string;
    area: string;
    alerta?: boolean;
    texto: (cuantos: number) => string;
};

const plural = (cuantos: number, uno: string, varios: string) =>
    cuantos === 1 ? uno : varios;

/** En el orden en que se atienden: las alertas primero. */
const GRUPOS: Grupo[] = [
    {
        clave: 'no_apto',
        area: 'Taller',
        alerta: true,
        texto: (n) => `${n} ${plural(n, 'no apto', 'no aptos')} en taller`,
    },
    {
        clave: 'atrasada',
        area: 'Programación',
        alerta: true,
        texto: (n) =>
            `${n} ${plural(n, 'llegada atrasada', 'llegadas atrasadas')}`,
    },
    {
        clave: 'taller',
        area: 'Taller',
        texto: (n) => `${n} ${plural(n, 'espera', 'esperan')} el OK de taller`,
    },
    {
        clave: 'abastecimiento_ida',
        area: 'Abastecimiento',
        texto: (n) => `${n} sin el combustible de ida completo`,
    },
    {
        clave: 'en_ida',
        area: 'Programación',
        texto: (n) => `${n} ${plural(n, 'lista', 'listas')} para su GR de ida`,
    },
    {
        clave: 'regreso',
        area: 'Programación',
        texto: (n) => `${n} sin el regreso programado`,
    },
    {
        clave: 'abastecimiento_vuelta',
        area: 'Abastecimiento',
        texto: (n) => `${n} sin el combustible de vuelta completo`,
    },
    {
        clave: 'en_vuelta',
        area: 'Programación',
        texto: (n) => `${n} ${plural(n, 'espera', 'esperan')} su GR de vuelta`,
    },
    {
        clave: 'cerrado',
        area: 'Liquidación',
        texto: (n) => `${n} por liquidar y cerrar`,
    },
];

/**
 * En qué grupo de «Para atender» cae el ticket, según lo que le sigue. Una
 * llegada que todavía no vence no es tarea: el camión sigue en camino.
 */
export function pendienteDe(vuelta: TarjetaVuelta): string | null {
    const siguiente = vuelta.siguiente;

    if (!siguiente) {
        return null;
    }

    switch (siguiente.clave) {
        case 'programado':
            return null;
        case 'taller':
            return siguiente.estado === 'alerta' ? 'no_apto' : 'taller';
        case 'llego':
            return siguiente.estado === 'alerta' ? 'atrasada' : null;
        default:
            return siguiente.clave;
    }
}

function placas(vueltas: TarjetaVuelta[]): string {
    const nombres = vueltas.map((vuelta) => formatearPlaca(vuelta.placa));

    return nombres.length > 4
        ? `${nombres.slice(0, 3).join(', ')} y ${nombres.length - 3} más`
        : nombres.join(', ');
}

/**
 * Lo primero que se ve en la programación: lo que hay que hacer, agrupado
 * por lo que le falta a cada ticket y con el área que lo hace. Tocar un
 * grupo deja en la lista solo esos tickets.
 */
export function ParaAtender({
    vueltas,
    guiasPorAsignar,
    filtro,
    onFiltrar,
}: {
    vueltas: TarjetaVuelta[];
    guiasPorAsignar: number;
    filtro: string | null;
    onFiltrar: (clave: string | null) => void;
}) {
    // En el celular se abre a pedido: la lista va primero.
    const [abierto, setAbierto] = useState(false);

    const grupos = GRUPOS.map((grupo) => ({
        ...grupo,
        vueltas: vueltas.filter(
            (vuelta) => pendienteDe(vuelta) === grupo.clave,
        ),
    })).filter((grupo) => grupo.vueltas.length > 0);
    const alertas = grupos
        .filter((grupo) => grupo.alerta)
        .reduce((suma, grupo) => suma + grupo.vueltas.length, 0);
    const total = grupos.length + (guiasPorAsignar > 0 ? 1 : 0);

    if (total === 0) {
        return (
            <p className="rounded-xl border bg-card px-4 py-3 text-sm text-muted-foreground">
                Nada para atender: todos los tickets van al día.
            </p>
        );
    }

    return (
        <section
            aria-label="Para atender"
            className="overflow-hidden rounded-xl border bg-card"
        >
            <button
                type="button"
                onClick={() => setAbierto(!abierto)}
                aria-expanded={abierto}
                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left md:pointer-events-none"
            >
                <span className="text-sm font-semibold">
                    Para atender{' '}
                    <span className="font-medium text-muted-foreground">
                        · {total}
                    </span>
                    {alertas > 0 && (
                        <span className="font-medium text-red-700 dark:text-red-400">
                            {' '}
                            · {alertas} con alerta
                        </span>
                    )}
                </span>
                <ChevronDown
                    className={cn(
                        'size-4 text-muted-foreground transition md:hidden',
                        abierto && 'rotate-180',
                    )}
                />
            </button>
            <ul className={cn('border-t', !abierto && 'max-md:hidden')}>
                {grupos.map((grupo) => {
                    const activo = filtro === grupo.clave;

                    return (
                        <li
                            key={grupo.clave}
                            className="border-b last:border-b-0"
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    onFiltrar(activo ? null : grupo.clave)
                                }
                                aria-pressed={activo}
                                data-pendiente={grupo.clave}
                                className={cn(
                                    'grid w-full grid-cols-[1rem_minmax(0,1fr)_auto] items-center gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-muted/50 md:grid-cols-[1rem_minmax(0,1fr)_8rem_6rem] pointer-coarse:min-h-11',
                                    activo && 'bg-accent hover:bg-accent',
                                )}
                            >
                                {grupo.alerta ? (
                                    <CircleAlert className="size-4 text-red-700 dark:text-red-400" />
                                ) : (
                                    <span className="size-1.5 justify-self-center rounded-full bg-muted-foreground/60" />
                                )}
                                <span className="min-w-0">
                                    <span className="font-medium">
                                        {grupo.texto(grupo.vueltas.length)}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        · {placas(grupo.vueltas)}
                                    </span>
                                </span>
                                <span className="justify-self-start rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground max-md:hidden">
                                    {grupo.area}
                                </span>
                                <span className="flex items-center justify-end gap-1 text-xs font-medium text-primary">
                                    {activo ? (
                                        <>
                                            <X className="size-3.5" />
                                            Quitar
                                        </>
                                    ) : (
                                        'Ver'
                                    )}
                                </span>
                            </button>
                        </li>
                    );
                })}
                {guiasPorAsignar > 0 && (
                    <li className="border-b last:border-b-0">
                        <Link
                            href={guiasRutas.index().url}
                            className="grid w-full grid-cols-[1rem_minmax(0,1fr)_auto] items-center gap-3 px-4 py-2.5 text-sm transition hover:bg-muted/50 md:grid-cols-[1rem_minmax(0,1fr)_8rem_6rem] pointer-coarse:min-h-11"
                        >
                            <span className="size-1.5 justify-self-center rounded-full bg-muted-foreground/60" />
                            <span className="min-w-0 font-medium">
                                {guiasPorAsignar}{' '}
                                {plural(
                                    guiasPorAsignar,
                                    'GR espera su ticket',
                                    'GR esperan su ticket',
                                )}
                            </span>
                            <span className="justify-self-start rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground max-md:hidden">
                                Programación
                            </span>
                            <span className="text-right text-xs font-medium text-primary">
                                Asignar
                            </span>
                        </Link>
                    </li>
                )}
            </ul>
        </section>
    );
}
