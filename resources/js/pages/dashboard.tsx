import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Container,
    FileWarning,
    Truck,
    Users,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    XAxis,
    YAxis,
} from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { clienteColor } from '@/lib/cliente-color';
import { dashboard } from '@/routes';

type ConteoCategoria = { label: string; valor: number };
type ConteoCarga = { tipo: string; label: string; valor: number };
type ConteoCliente = { cliente: string; valor: number };

type Props = {
    resumen: {
        tractos: number;
        carretas: number;
        operativos: number;
        conductores: number;
        novedadesActivas: number;
        documentosVencidos: number;
    };
    novedadesPorTipo: ConteoCategoria[];
    filtroMes: string | null;
    mesesDisponibles: string[];
    cargaMinsur: ConteoCarga[];
    viajesPorCliente: ConteoCliente[];
    clientesParticulares: ConteoCliente[];
};

const novedadesConfig = {
    valor: { label: 'Unidades', color: '#f59e0b' },
} satisfies ChartConfig;

/**
 * No son marcas —son categorías de mineral— así que van con una paleta
 * neutra propia en vez de `clienteColor()`. Concentrado en el mismo azul del
 * chip de Minsur porque es su carga insignia; el resto, tonos que evocan lo
 * que es cada uno (plata para metálico, tierra para escoria).
 */
const cargaMinsurConfig = {
    concentrado: { label: 'Concentrado', color: 'var(--color-blue-600)' },
    metalico: { label: 'Metálico', color: 'var(--color-slate-400)' },
    escoria: { label: 'Escoria', color: 'var(--color-stone-600)' },
    materiales: { label: 'Materiales', color: 'var(--color-amber-500)' },
    particular: { label: 'Particular', color: 'var(--color-zinc-400)' },
} satisfies ChartConfig;

const viajesPorClienteConfig = {
    valor: { label: 'Viajes' },
} satisfies ChartConfig;

/** `2026-08` → «agosto 2026», para el selector de mes. */
function etiquetaMes(mes: string): string {
    const [anio, mesNumero] = mes.split('-');
    const fecha = new Date(Number(anio), Number(mesNumero) - 1, 1);

    return fecha.toLocaleDateString('es-PE', {
        month: 'long',
        year: 'numeric',
    });
}

export default function Dashboard({
    resumen,
    novedadesPorTipo,
    filtroMes,
    mesesDisponibles,
    cargaMinsur,
    viajesPorCliente,
    clientesParticulares,
}: Props) {
    const cambiarMes = (mes: string) => {
        router.get(
            dashboard().url,
            { mes },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hayNovedades = novedadesPorTipo.some((novedad) => novedad.valor > 0);
    const hayCargaMinsur = cargaMinsur.some((carga) => carga.valor > 0);
    const hayViajesPorCliente = viajesPorCliente.length > 0;
    const hayClientesParticulares = clientesParticulares.length > 0;
    const alturaViajesPorCliente = Math.max(192, viajesPorCliente.length * 32);
    const alturaClientesParticulares = Math.max(
        128,
        clientesParticulares.length * 32,
    );

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Dashboard" />

            <div>
                <p className="text-sm text-muted-foreground">
                    Estado general de las unidades y la operación del día.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Tarjeta
                    label="Tractos"
                    valor={resumen.tractos}
                    icon={<Truck className="size-5" />}
                />
                <Tarjeta
                    label="Carretas"
                    valor={resumen.carretas}
                    icon={<Container className="size-5" />}
                />
                <Tarjeta
                    label="Conductores activos"
                    valor={resumen.conductores}
                    icon={<Users className="size-5" />}
                />
                <Tarjeta
                    label="No programables hoy"
                    valor={resumen.novedadesActivas}
                    detalle="unidades con novedad vigente"
                    icon={<AlertTriangle className="size-5" />}
                    tono={resumen.novedadesActivas > 0 ? 'ambar' : 'normal'}
                />
                <Tarjeta
                    label="Documentos vencidos"
                    valor={resumen.documentosVencidos}
                    detalle="de fierros y conductores"
                    icon={<FileWarning className="size-5" />}
                    tono={resumen.documentosVencidos > 0 ? 'rojo' : 'normal'}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel
                    titulo="Clasificación de carga — Minsur"
                    extra={
                        mesesDisponibles.length > 0 && (
                            <Select
                                value={filtroMes ?? undefined}
                                onValueChange={cambiarMes}
                            >
                                <SelectTrigger
                                    size="sm"
                                    aria-label="Mes de los viajes de Minsur"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {mesesDisponibles.map((mes) => (
                                        <SelectItem key={mes} value={mes}>
                                            {etiquetaMes(mes)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )
                    }
                >
                    {hayCargaMinsur ? (
                        <ChartContainer
                            config={cargaMinsurConfig}
                            className="h-64 w-full"
                        >
                            <BarChart
                                data={cargaMinsur}
                                layout="vertical"
                                margin={{ left: 4, right: 28 }}
                            >
                                <CartesianGrid horizontal={false} />
                                <XAxis type="number" hide />
                                <YAxis
                                    dataKey="label"
                                    type="category"
                                    tickLine={false}
                                    axisLine={false}
                                    width={90}
                                    tick={{ fontSize: 12 }}
                                />
                                <ChartTooltip
                                    cursor={{ fill: 'var(--muted)' }}
                                    content={<ChartTooltipContent hideLabel />}
                                />
                                <Bar dataKey="valor" radius={0} barSize={22}>
                                    {cargaMinsur.map((carga) => (
                                        <Cell
                                            key={carga.tipo}
                                            fill={`var(--color-${carga.tipo})`}
                                        />
                                    ))}
                                    <LabelList
                                        dataKey="valor"
                                        position="right"
                                        className="fill-foreground"
                                        fontSize={12}
                                    />
                                </Bar>
                            </BarChart>
                        </ChartContainer>
                    ) : (
                        <EstadoVacio texto="Sin viajes de Minsur en este mes." />
                    )}
                </Panel>

                <Panel titulo="Novedades activas por tipo">
                    {hayNovedades ? (
                        <ChartContainer
                            config={novedadesConfig}
                            className="h-64 w-full"
                        >
                            <BarChart
                                data={novedadesPorTipo}
                                layout="vertical"
                                margin={{ left: 4, right: 28 }}
                            >
                                <CartesianGrid horizontal={false} />
                                <XAxis type="number" hide />
                                <YAxis
                                    dataKey="label"
                                    type="category"
                                    tickLine={false}
                                    axisLine={false}
                                    width={150}
                                    tick={{ fontSize: 12 }}
                                />
                                <ChartTooltip
                                    cursor={{ fill: 'var(--muted)' }}
                                    content={<ChartTooltipContent hideLabel />}
                                />
                                <Bar
                                    dataKey="valor"
                                    fill="var(--color-valor)"
                                    radius={0}
                                    barSize={18}
                                >
                                    <LabelList
                                        dataKey="valor"
                                        position="right"
                                        className="fill-foreground"
                                        fontSize={12}
                                    />
                                </Bar>
                            </BarChart>
                        </ChartContainer>
                    ) : (
                        <EstadoVacio texto="Sin novedades: toda la flota está programable." />
                    )}
                </Panel>
            </div>

            <Panel titulo="Viajes por cliente (fuera de Minsur)">
                {hayViajesPorCliente ? (
                    <ChartContainer
                        config={viajesPorClienteConfig}
                        className="w-full"
                        style={{ height: alturaViajesPorCliente }}
                    >
                        <BarChart
                            data={viajesPorCliente}
                            layout="vertical"
                            margin={{ left: 4, right: 28 }}
                        >
                            <CartesianGrid horizontal={false} />
                            <XAxis type="number" hide />
                            <YAxis
                                dataKey="cliente"
                                type="category"
                                tickLine={false}
                                axisLine={false}
                                width={260}
                                tick={{ fontSize: 11 }}
                            />
                            <ChartTooltip
                                cursor={{ fill: 'var(--muted)' }}
                                content={<ChartTooltipContent hideLabel />}
                            />
                            <Bar dataKey="valor" radius={0} barSize={18}>
                                {viajesPorCliente.map((item) => (
                                    <Cell
                                        key={item.cliente}
                                        fill={clienteColor(item.cliente).chart}
                                    />
                                ))}
                                <LabelList
                                    dataKey="valor"
                                    position="right"
                                    className="fill-foreground"
                                    fontSize={12}
                                />
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                ) : (
                    <EstadoVacio texto="Aún no hay viajes de otros clientes registrados." />
                )}
            </Panel>

            <Panel titulo="Clientes particulares">
                {hayClientesParticulares ? (
                    <ChartContainer
                        config={viajesPorClienteConfig}
                        className="w-full"
                        style={{ height: alturaClientesParticulares }}
                    >
                        <BarChart
                            data={clientesParticulares}
                            layout="vertical"
                            margin={{ left: 4, right: 28 }}
                        >
                            <CartesianGrid horizontal={false} />
                            <XAxis type="number" hide />
                            <YAxis
                                dataKey="cliente"
                                type="category"
                                tickLine={false}
                                axisLine={false}
                                width={260}
                                tick={{ fontSize: 11 }}
                            />
                            <ChartTooltip
                                cursor={{ fill: 'var(--muted)' }}
                                content={<ChartTooltipContent hideLabel />}
                            />
                            <Bar dataKey="valor" radius={0} barSize={18}>
                                {clientesParticulares.map((item) => (
                                    <Cell
                                        key={item.cliente}
                                        fill={clienteColor(item.cliente).chart}
                                    />
                                ))}
                                <LabelList
                                    dataKey="valor"
                                    position="right"
                                    className="fill-foreground"
                                    fontSize={12}
                                />
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                ) : (
                    <EstadoVacio texto="Sin clientes particulares (persona natural) registrados." />
                )}
            </Panel>
        </div>
    );
}

const tonoValor: Record<'normal' | 'ambar' | 'rojo', string> = {
    normal: '',
    ambar: 'text-amber-700 dark:text-amber-500',
    rojo: 'text-red-700 dark:text-red-500',
};

function Tarjeta({
    label,
    valor,
    detalle,
    icon,
    tono = 'normal',
}: {
    label: string;
    valor: number;
    detalle?: string;
    icon: React.ReactNode;
    tono?: 'normal' | 'ambar' | 'rojo';
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{label}</p>
                <span className="text-muted-foreground">{icon}</span>
            </div>
            <p
                className={`mt-2 text-3xl font-semibold tabular-nums ${tonoValor[tono]}`}
            >
                {valor}
            </p>
            {detalle && (
                <p className="mt-1 text-xs text-muted-foreground">{detalle}</p>
            )}
        </div>
    );
}

function Panel({
    titulo,
    extra,
    children,
}: {
    titulo: string;
    extra?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-5">
                <h2 className="text-sm font-semibold">{titulo}</h2>
                {extra}
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function EstadoVacio({ texto }: { texto: string }) {
    return (
        <div className="flex h-64 items-center justify-center text-center text-sm text-muted-foreground">
            {texto}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard().url }],
};
