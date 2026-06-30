import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/combustible/chart-card';
import { formatearSoles } from '@/lib/format';

type Punto = { mes: string; acumulado: number };

export function ChartCosto({ data }: { data: Punto[] }) {
    const sinDatos = data.every((d) => d.acumulado === 0);

    return (
        <ChartCard
            title="Costo acumulado"
            subtitle="Gasto en combustible a lo largo del año"
            vacio={sinDatos}
        >
            <ResponsiveContainer width="100%" height={240}>
                <AreaChart
                    data={data}
                    margin={{ top: 8, right: 12, left: -4, bottom: 0 }}
                >
                    <defs>
                        <linearGradient
                            id="costoFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="#047857"
                                stopOpacity={0.3}
                            />
                            <stop
                                offset="100%"
                                stopColor="#047857"
                                stopOpacity={0}
                            />
                        </linearGradient>
                    </defs>
                    <CartesianGrid
                        strokeDasharray="3 3"
                        className="stroke-border"
                    />
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
                        tickFormatter={(value) => formatearSoles(Number(value))}
                    />
                    <Tooltip
                        formatter={(value) => [
                            formatearSoles(Number(value)),
                            'Acumulado',
                        ]}
                        contentStyle={{ fontSize: 12, borderRadius: 8 }}
                    />
                    <Area
                        type="monotone"
                        dataKey="acumulado"
                        stroke="#047857"
                        strokeWidth={2}
                        fill="url(#costoFill)"
                    />
                </AreaChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
