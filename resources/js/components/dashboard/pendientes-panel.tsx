import { Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck } from 'lucide-react';
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

/** El color del número según cuánto urge; en cero, verde «al día». */
const TONO: Record<Pendiente['tono'], string> = {
    alerta: 'text-red-600 dark:text-red-400',
    aviso: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-600 dark:text-sky-400',
};

const BORDE: Record<Pendiente['tono'], string> = {
    alerta: 'border-l-red-500',
    aviso: 'border-l-amber-500',
    info: 'border-l-sky-500',
};

function Tarjeta({ pendiente }: { pendiente: Pendiente }) {
    const alDia = pendiente.cantidad === 0;

    return (
        <article
            className={cn(
                'flex flex-col gap-3 rounded-xl border border-l-4 bg-card p-4',
                alDia ? 'border-l-emerald-500' : BORDE[pendiente.tono],
            )}
        >
            <header className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        {pendiente.area}
                    </p>
                    <h3 className="text-sm font-semibold">
                        {pendiente.titulo}
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        {pendiente.ayuda}
                    </p>
                </div>
                {alDia ? (
                    <span className="flex shrink-0 items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <CircleCheck className="size-4" />
                        Al día
                    </span>
                ) : (
                    <span
                        className={cn(
                            'shrink-0 text-3xl leading-none font-bold tabular-nums',
                            TONO[pendiente.tono],
                        )}
                    >
                        {pendiente.cantidad}
                    </span>
                )}
            </header>

            {pendiente.items.length > 0 && (
                <ul className="flex flex-col divide-y rounded-lg border text-sm">
                    {pendiente.items.map((item) => (
                        <li key={item.href + item.texto}>
                            <Link
                                href={item.href}
                                className="flex items-center justify-between gap-2 px-3 py-1.5 hover:bg-accent/50"
                            >
                                <span className="truncate font-mono text-xs">
                                    {item.texto}
                                </span>
                                {item.detalle && (
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {item.detalle}
                                    </span>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {!alDia && (
                <Link
                    href={pendiente.href}
                    className="mt-auto flex items-center gap-1 self-start text-xs font-medium text-primary hover:underline"
                >
                    {pendiente.cantidad > pendiente.items.length &&
                    pendiente.items.length > 0
                        ? `Ver los ${pendiente.cantidad}`
                        : 'Ir a resolverlo'}
                    <ArrowRight className="size-3.5" />
                </Link>
            )}
        </article>
    );
}

/**
 * Lo primero que se ve al entrar: lo que le toca hoy a quien entró, según lo
 * que puede hacer. Cada tarjeta lleva directo a donde se resuelve.
 */
export function PendientesPanel({ pendientes }: { pendientes: Pendiente[] }) {
    if (pendientes.length === 0) {
        return null;
    }

    const total = pendientes.reduce(
        (suma, pendiente) => suma + pendiente.cantidad,
        0,
    );

    return (
        <section className="flex flex-col gap-3">
            <div>
                <h2 className="text-lg font-semibold tracking-tight">
                    Lo que te toca hoy
                </h2>
                <p className="text-sm text-muted-foreground">
                    {total === 0
                        ? 'Todo al día. Cuando algo necesite tu atención, aparece aquí.'
                        : 'Toca una tarjeta o un ticket para ir directo a resolverlo.'}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {pendientes.map((pendiente) => (
                    <Tarjeta key={pendiente.clave} pendiente={pendiente} />
                ))}
            </div>
        </section>
    );
}
