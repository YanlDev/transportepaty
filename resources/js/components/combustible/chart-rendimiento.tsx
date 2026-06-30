import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/combustible/chart-card';

type Punto = { fecha: string; rendimiento: number };

type Props = {
    data: Punto[];
    action?: React.ReactNode;
    href?: string;
};

export function ChartRendimiento({ data, action, href }: Props) {
    return (
        <ChartCard
            title="Rendimiento por carga"
            vacio={data.length < 2}
            action={action}
            href={href}
        >
            <ResponsiveContainer width="100%" height={240}>
                <LineChart
                    data={data}
                    margin={{ top: 8, right: 12, left: -12, bottom: 0 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        className="stroke-border"
                    />
                    <XAxis
                        dataKey="fecha"
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
                        formatter={(value) => [
                            `${value} km/gal`,
                            'Rendimiento',
                        ]}
                        contentStyle={{ fontSize: 12, borderRadius: 8 }}
                    />
                    <Line
                        type="monotone"
                        dataKey="rendimiento"
                        stroke="#047857"
                        strokeWidth={2}
                        dot={{ r: 3 }}
                        activeDot={{ r: 5 }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
