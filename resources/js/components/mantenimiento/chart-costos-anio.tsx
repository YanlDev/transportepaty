import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { formatearSoles } from '@/lib/format';
import type { CostosAnio } from '@/types/fleet';

const COLORES: Record<string, string> = {
    combustible: '#0ea5e9',
    mantenimiento: '#047857',
};

const FALLBACK = ['#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6'];

export function ChartCostosAnio({ costos }: { costos: CostosAnio }) {
    const total = costos.total;

    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <h2 className="mb-1 text-sm font-semibold text-foreground">
                Costos del año {costos.anio}
            </h2>

            {total === 0 ? (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    Sin costos registrados este año.
                </p>
            ) : (
                <div className="flex flex-col items-center gap-4 sm:flex-row">
                    <div className="relative h-[180px] w-[180px] shrink-0">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={costos.categorias}
                                    dataKey="monto"
                                    nameKey="label"
                                    innerRadius={60}
                                    outerRadius={88}
                                    paddingAngle={2}
                                >
                                    {costos.categorias.map((c, i) => (
                                        <Cell
                                            key={c.clave}
                                            fill={
                                                COLORES[c.clave] ??
                                                FALLBACK[i % FALLBACK.length]
                                            }
                                        />
                                    ))}
                                </Pie>
                                <Tooltip
                                    formatter={(v, n) => [
                                        formatearSoles(Number(v)),
                                        n,
                                    ]}
                                    contentStyle={{
                                        fontSize: 12,
                                        borderRadius: 8,
                                    }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-xs text-muted-foreground">
                                Total
                            </span>
                            <span className="text-base font-semibold text-foreground">
                                {formatearSoles(total)}
                            </span>
                        </div>
                    </div>

                    <ul className="flex flex-1 flex-col gap-2 text-sm">
                        {costos.categorias.map((c, i) => (
                            <li
                                key={c.clave}
                                className="flex items-center justify-between gap-3"
                            >
                                <span className="flex items-center gap-2 text-muted-foreground">
                                    <span
                                        className="size-2.5 rounded-full"
                                        style={{
                                            backgroundColor:
                                                COLORES[c.clave] ??
                                                FALLBACK[i % FALLBACK.length],
                                        }}
                                    />
                                    {c.label}
                                </span>
                                <span className="text-foreground tabular-nums">
                                    {formatearSoles(c.monto)}
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        ({Math.round((c.monto / total) * 100)}%)
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
