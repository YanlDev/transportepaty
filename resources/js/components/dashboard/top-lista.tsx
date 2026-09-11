import { cn } from '@/lib/utils';

export function TopLista({
    titulo,
    items,
    vacio,
}: {
    titulo: string;
    items: { clave: string; label: string; valor: number }[];
    vacio: string;
}) {
    const colores = [
        'bg-red-500 text-white',
        'bg-amber-500 text-amber-950',
        'bg-zinc-400 text-zinc-950',
        'bg-violet-500 text-white',
        'bg-blue-500 text-white',
    ];

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="text-sm font-semibold">{titulo}</h2>
            </div>

            {items.length === 0 ? (
                <p className="p-6 text-center text-sm text-muted-foreground">
                    {vacio}
                </p>
            ) : (
                <ul className="divide-y">
                    {items.map((item, indice) => (
                        <li
                            key={item.clave}
                            className="flex items-center gap-3 p-3"
                        >
                            <span
                                className={cn(
                                    'grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums',
                                    colores[indice] ?? colores[4],
                                )}
                            >
                                {indice + 1}
                            </span>
                            <span
                                className="min-w-0 flex-1 truncate text-sm"
                                title={item.label}
                            >
                                {item.label}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {item.valor}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
