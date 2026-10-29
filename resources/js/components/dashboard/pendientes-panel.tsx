import { Link } from '@inertiajs/react';
import { ChevronRight, CircleCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Pendiente = {
    area: string;
    clave: string;
    titulo: string;
    ayuda: string;
    cantidad: number;
    tono: 'alerta' | 'aviso' | 'info';
    /** Dónde se resuelve. */
    href: string;
    /** Los primeros; el resto está en `href`. */
    items: { texto: string; detalle: string | null; href: string }[];
};

/** Lo más urgente arriba. */
const ORDEN: Record<Pendiente['tono'], number> = {
    alerta: 0,
    aviso: 1,
    info: 2,
};

const PUNTO: Record<Pendiente['tono'], string> = {
    alerta: 'bg-red-500',
    aviso: 'bg-amber-500',
    info: 'bg-sky-500',
};

const NUMERO: Record<Pendiente['tono'], string> = {
    alerta: 'bg-red-500/10 text-red-700 dark:text-red-300',
    aviso: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    info: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
};

/** Cuántos tickets se ven como chips en cada fila. */
const CHIPS = 3;

/**
 * Los pendientes de quien entró, como un panel más del tablero: una fila por
 * pendiente con algo que hacer (lo urgente primero) y sus tickets a mano; lo
 * que está al día va junto en una línea al pie.
 */
export function PendientesPanel({ pendientes }: { pendientes: Pendiente[] }) {
    if (pendientes.length === 0) {
        return null;
    }

    const conAlgo = pendientes
        .filter((pendiente) => pendiente.cantidad > 0)
        .sort((a, b) => ORDEN[a.tono] - ORDEN[b.tono]);
    const alDia = pendientes.filter((pendiente) => pendiente.cantidad === 0);

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-3 border-b p-4">
                <h2 className="text-sm font-semibold">Pendientes de hoy</h2>
                <span className="text-xs text-muted-foreground">
                    {conAlgo.length === 0
                        ? 'Todo al día'
                        : `${conAlgo.length} por resolver`}
                </span>
            </div>

            {conAlgo.length > 0 && (
                <ul className="divide-y">
                    {conAlgo.map((pendiente) => (
                        <li
                            key={pendiente.clave}
                            className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-3"
                        >
                            <Link
                                href={pendiente.href}
                                className="group flex min-w-0 items-center gap-3 sm:w-72 sm:shrink-0"
                            >
                                <span
                                    className={cn(
                                        'grid h-6 min-w-6 shrink-0 place-items-center rounded-full px-1.5 text-xs font-bold tabular-nums',
                                        NUMERO[pendiente.tono],
                                    )}
                                >
                                    {pendiente.cantidad}
                                </span>
                                <span className="min-w-0">
                                    <span className="flex items-center gap-1 text-sm font-medium group-hover:underline">
                                        {pendiente.titulo}
                                        <ChevronRight className="size-3.5 text-muted-foreground" />
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {pendiente.area}
                                    </span>
                                </span>
                            </Link>

                            {pendiente.items.length > 0 && (
                                <div className="flex min-w-0 flex-wrap items-center gap-1.5 pl-9 sm:pl-0">
                                    {pendiente.items
                                        .slice(0, CHIPS)
                                        .map((item) => (
                                            <Link
                                                key={item.href + item.texto}
                                                href={item.href}
                                                className="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs hover:border-foreground/30 hover:bg-accent/50"
                                            >
                                                <span
                                                    className={cn(
                                                        'size-1.5 rounded-full',
                                                        PUNTO[pendiente.tono],
                                                    )}
                                                />
                                                <span className="font-mono">
                                                    {item.texto}
                                                </span>
                                                {item.detalle && (
                                                    <span className="text-muted-foreground">
                                                        {item.detalle}
                                                    </span>
                                                )}
                                            </Link>
                                        ))}
                                    {pendiente.cantidad > CHIPS && (
                                        <Link
                                            href={pendiente.href}
                                            className="text-xs text-muted-foreground hover:text-foreground"
                                        >
                                            +{pendiente.cantidad - CHIPS} más
                                        </Link>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {alDia.length > 0 && (
                <p
                    className={cn(
                        'flex flex-wrap items-center gap-x-1.5 gap-y-1 px-4 py-3 text-xs text-muted-foreground',
                        conAlgo.length > 0 && 'border-t',
                    )}
                >
                    <CircleCheck className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                    <span className="font-medium text-emerald-700 dark:text-emerald-400">
                        Al día:
                    </span>
                    {alDia.map((pendiente) => pendiente.titulo).join(' · ')}
                </p>
            )}
        </section>
    );
}
