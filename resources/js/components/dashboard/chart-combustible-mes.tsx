import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatearSoles } from '@/lib/format';
import type { CombustibleMes } from '@/types/dashboard';

export function ChartCombustibleMes({ serie }: { serie: CombustibleMes[] }) {
    const total = serie.reduce((acc, m) => acc + m.total, 0);

    if (total === 0) {
        return (
            <p className="grid h-[220px] place-items-center text-center text-sm text-muted-foreground">
                Aún no hay cargas de combustible registradas.
            </p>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={220}>
            <BarChart
                data={serie}
                margin={{ top: 8, right: 8, bottom: 0, left: -8 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    vertical={false}
                    className="stroke-border"
                />
                <XAxis
                    dataKey="mes"
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                    className="fill-muted-foreground"
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                    width={56}
                    tickFormatter={(v) =>
                        `S/ ${Number(v).toLocaleString('es-PE')}`
                    }
                    className="fill-muted-foreground"
                />
                <Tooltip
                    cursor={{ fill: 'var(--muted)', opacity: 0.4 }}
                    formatter={(v) => [
                        formatearSoles(Number(v)),
                        'Combustible',
                    ]}
                    contentStyle={{ fontSize: 12, borderRadius: 8 }}
                />
                <Bar
                    dataKey="total"
                    fill="#047857"
                    radius={[6, 6, 0, 0]}
                    maxBarSize={48}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}
