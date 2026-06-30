import { useMemo } from 'react';
import {
    Bar,
    BarChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/combustible/chart-card';
import { formatearSoles } from '@/lib/format';
import type { Mantenimiento } from '@/types/fleet';

export function ChartCostosMantenimiento({
    mantenimientos,
}: {
    mantenimientos: Mantenimiento[];
}) {
    const porMes = useMemo(() => costoPorMes(mantenimientos), [mantenimientos]);

    return (
        <ChartCard
            title="Costo de mantenimiento por mes"
            subtitle="Últimos 12 meses"
            vacio={porMes.every((m) => m.costo === 0)}
        >
            <ResponsiveContainer width="100%" height={240}>
                <BarChart
                    data={porMes}
                    margin={{ top: 8, right: 12, left: -4, bottom: 0 }}
                >
                    <XAxis
                        dataKey="mes"
                        tick={{ fontSize: 11 }}
                        stroke="currentColor"
                        className="text-muted-foreground"
                    />
                    <YAxis
                        tick={{ fontSize: 11 }}
                        stroke="currentColor"
                        className="text-muted-foreground"
                        width={60}
                        tickFormatter={(v) => formatearSoles(Number(v))}
                    />
                    <Tooltip
                        formatter={(v) => [formatearSoles(Number(v)), 'Costo']}
                        contentStyle={{ fontSize: 12, borderRadius: 8 }}
                        cursor={{ fill: 'rgba(4,120,87,0.08)' }}
                    />
                    <Bar dataKey="costo" fill="#047857" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}

function costoPorMes(
    mantenimientos: Mantenimiento[],
): { mes: string; costo: number }[] {
    const ahora = new Date();
    const buckets: { key: string; mes: string; costo: number }[] = [];

    for (let i = 11; i >= 0; i--) {
        const d = new Date(ahora.getFullYear(), ahora.getMonth() - i, 1);
        buckets.push({
            key: `${d.getFullYear()}-${d.getMonth()}`,
            mes: d.toLocaleDateString('es-PE', { month: 'short' }),
            costo: 0,
        });
    }

    const indice = new Map(buckets.map((b, i) => [b.key, i]));

    for (const m of mantenimientos) {
        const d = new Date(m.fecha_realizado);
        const i = indice.get(`${d.getFullYear()}-${d.getMonth()}`);

        if (i !== undefined) {
            buckets[i].costo += m.costo_total ?? 0;
        }
    }

    return buckets.map((b) => ({
        mes: b.mes,
        costo: Math.round(b.costo * 100) / 100,
    }));
}
