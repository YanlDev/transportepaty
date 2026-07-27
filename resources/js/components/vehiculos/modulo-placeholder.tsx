type Props = {
    icon: React.ReactNode;
    title: string;
    description: string;
    variante: 'grafico' | 'lista';
};

/**
 * "Coming soon" placeholder card for vehicle modules not yet implemented
 * (fuel usage, maintenance history, etc.).
 */
export function ModuloPlaceholder({ icon, title, description, variante }: Props) {
    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-semibold text-foreground">{title}</h2>
                <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                    Próximamente
                </span>
            </div>

            {variante === 'grafico' ? (
                <div className="flex h-28 items-end justify-between gap-2 opacity-40">
                    {[45, 70, 55, 85, 60, 75].map((alto, i) => (
                        <div
                            key={i}
                            className="flex-1 rounded-t bg-navy-300"
                            style={{ height: `${alto}%` }}
                        />
                    ))}
                </div>
            ) : (
                <div className="space-y-2.5 opacity-40">
                    {[0, 1, 2].map((i) => (
                        <div key={i} className="h-10 rounded-lg bg-muted" />
                    ))}
                </div>
            )}

            <div className="mt-4 flex items-center gap-2 text-muted-foreground">
                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-muted">
                    {icon}
                </span>
                <p className="text-xs">{description}</p>
            </div>
        </section>
    );
}
