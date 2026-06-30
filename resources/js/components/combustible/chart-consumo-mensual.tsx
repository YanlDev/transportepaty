import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/combustible/chart-card';

type Punto = { mes: string; galones: number };

export function ChartConsumoMensual({ data }: { data: Punto[] }) {
    const sinDatos = data.every((d) => d.galones === 0);

    return (
        <ChartCard
            title="Consumo mensual"
            subtitle="Galones cargados por mes"
            vacio={sinDatos}
        >
            <ResponsiveContainer width="100%" height={240}>
                <BarChart
                    data={data}
                    margin={{ top: 8, right: 12, left: -12, bottom: 0 }}
                >
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
                        width={40}
                    />
                    <Tooltip
                        formatter={(value) => [`${value} gal`, 'Galones']}
                        contentStyle={{ fontSize: 12, borderRadius: 8 }}
                        cursor={{ fill: 'rgba(4,120,87,0.08)' }}
                    />
                    <Bar
                        dataKey="galones"
                        fill="#047857"
                        radius={[4, 4, 0, 0]}
                    />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
