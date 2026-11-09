import { cn } from '@/lib/utils';

const TONOS = {
    vigente:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    abierto:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    anulado:
        'border-red-300 bg-red-50 text-red-700 line-through dark:border-red-800 dark:bg-red-950/40 dark:text-red-300',
    neutro: 'border-border bg-muted text-muted-foreground',
} as const;

/** La etiqueta de estado de un documento del almacén. */
export function EstadoDocumento({
    tono,
    children,
}: {
    tono: keyof typeof TONOS;
    children: React.ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                TONOS[tono],
            )}
        >
            {children}
        </span>
    );
}

/** Una cifra grande con su etiqueta, para los resúmenes de arriba. */
export function CifraAlmacen({
    valor,
    etiqueta,
    className,
}: {
    valor: React.ReactNode;
    etiqueta: string;
    className?: string;
}) {
    return (
        <div className="flex min-w-0 flex-col rounded-xl border bg-card p-3">
            <span
                className={cn(
                    'truncate text-2xl font-semibold tabular-nums',
                    className,
                )}
            >
                {valor}
            </span>
            <span className="text-xs text-muted-foreground">{etiqueta}</span>
        </div>
    );
}
