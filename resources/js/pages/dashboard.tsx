import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, FileWarning, Truck, Users } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    LabelList,
    XAxis,
    YAxis,
} from 'recharts';
import { show as showConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

/** Horizontes del filtro de vencimientos; deben calzar con el backend. */
const horizontes = [
    { value: '15', label: 'Próximos 15 días' },
    { value: '30', label: 'Próximos 30 días' },
];

type DocumentoPorVencer = {
    clave: string;
    /** Placa del vehículo o nombre del conductor, según a quién pertenezca. */
    titular: string;
    vehiculo_id: number | null;
    conductor_id: number | null;
    tipo_label: string;
    fecha_vencimiento: string | null;
    vencido: boolean;
};

type ConteoCategoria = { label: string; valor: number };
type SaludDocumental = {
    entidad: string;
    label: string;
    verde: number;
    ambar: number;
    rojo: number;
};

type Props = {
    resumen: {
        tractos: number;
        carretas: number;
        operativos: number;
        conductores: number;
        novedadesActivas: number;
        documentosVencidos: number;
    };
    filtros: { dias: number };
    documentosPorVencer: DocumentoPorVencer[];
    estadoFlota: ConteoCategoria[];
    fasesCiclo: ConteoCategoria[];
    novedadesPorTipo: ConteoCategoria[];
    saludDocumental: SaludDocumental[];
};

const estadoFlotaConfig = {
    valor: { label: 'Unidades', color: 'var(--chart-2)' },
} satisfies ChartConfig;

const fasesCicloConfig = {
    valor: { label: 'Unidades', color: 'var(--chart-2)' },
} satisfies ChartConfig;

const novedadesConfig = {
    valor: { label: 'Unidades', color: '#f59e0b' },
} satisfies ChartConfig;

const saludDocumentalConfig = {
    verde: { label: 'Al día', color: '#10b981' },
    ambar: { label: 'Por vencer', color: '#f59e0b' },
    rojo: { label: 'Con problemas', color: '#ef4444' },
} satisfies ChartConfig;

export default function Dashboard({
    resumen,
    filtros,
    documentosPorVencer,
    estadoFlota,
    fasesCiclo,
    novedadesPorTipo,
    saludDocumental,
}: Props) {
    const cambiarHorizonte = (dias: string) => {
        router.get(
            dashboard().url,
            { dias },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const totalFlota = resumen.tractos + resumen.carretas;
    const hayFlota = totalFlota > 0;
    const hayFasesRegistradas = fasesCiclo.some((fase) => fase.valor > 0);
    const hayNovedades = novedadesPorTipo.some((novedad) => novedad.valor > 0);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Dashboard" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Resumen de flota
                </h1>
                <p className="text-sm text-muted-foreground">
                    Estado general de las unidades, su documentación y la
                    operación del día.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Tarjeta
                    label="Flota operativa"
                    valor={resumen.operativos}
                    detalle={`de ${totalFlota} unidades`}
                    icon={<Truck className="size-5" />}
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
                <Panel titulo="Estado de la flota">
                    {hayFlota ? (
                        <ChartContainer
                            config={estadoFlotaConfig}
                            className="h-64 w-full"
                        >
                            <BarChart
                                data={estadoFlota}
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
                                    width={110}
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
                                    barSize={22}
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
                        <EstadoVacio texto="Aún no hay vehículos registrados." />
                    )}
                </Panel>

                <Panel titulo="Salud documental">
                    {hayFlota ? (
                        <ChartContainer
                            config={saludDocumentalConfig}
                            className="h-64 w-full"
                        >
                            <BarChart
                                data={saludDocumental}
                                layout="vertical"
                                margin={{ left: 4, right: 12 }}
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
                                    content={<ChartTooltipContent />}
                                />
                                <ChartLegend content={<ChartLegendContent />} />
                                <Bar
                                    dataKey="verde"
                                    stackId="salud"
                                    fill="var(--color-verde)"
                                    stroke="var(--card)"
                                    strokeWidth={2}
                                    radius={0}
                                    barSize={28}
                                />
                                <Bar
                                    dataKey="ambar"
                                    stackId="salud"
                                    fill="var(--color-ambar)"
                                    stroke="var(--card)"
                                    strokeWidth={2}
                                    radius={0}
                                    barSize={28}
                                />
                                <Bar
                                    dataKey="rojo"
                                    stackId="salud"
                                    fill="var(--color-rojo)"
                                    stroke="var(--card)"
                                    strokeWidth={2}
                                    radius={0}
                                    barSize={28}
                                />
                            </BarChart>
                        </ChartContainer>
                    ) : (
                        <EstadoVacio texto="Aún no hay vehículos registrados." />
                    )}
                </Panel>

                <Panel titulo="Unidades por fase del circuito">
                    {hayFasesRegistradas ? (
                        <ChartContainer
                            config={fasesCicloConfig}
                            className="h-64 w-full"
                        >
                            <BarChart
                                data={fasesCiclo}
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
                                    width={130}
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
                                    barSize={22}
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
                        <EstadoVacio texto="Todavía no hay reportes de ubicación para hoy." />
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

            <section className="rounded-xl border border-border bg-card">
                <div className="flex flex-wrap items-center gap-2 border-b p-5">
                    <AlertTriangle className="size-4 text-amber-500" />
                    <h2 className="text-sm font-semibold">
                        Documentos vencidos o por vencer
                    </h2>

                    <div className="ml-auto">
                        <Select
                            value={String(filtros.dias)}
                            onValueChange={cambiarHorizonte}
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label="Horizonte de vencimientos"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {horizontes.map((horizonte) => (
                                    <SelectItem
                                        key={horizonte.value}
                                        value={horizonte.value}
                                    >
                                        {horizonte.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {documentosPorVencer.length === 0 ? (
                    <p className="p-5 text-sm text-muted-foreground">
                        No hay documentos vencidos ni que venzan en los próximos{' '}
                        {filtros.dias} días.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Unidad / Conductor</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>Vence</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documentosPorVencer.map((documento) => (
                                <TableRow key={documento.clave}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={
                                                documento.vehiculo_id !== null
                                                    ? show(
                                                          documento.vehiculo_id,
                                                      )
                                                    : showConductor(
                                                          documento.conductor_id ??
                                                              0,
                                                      )
                                            }
                                            className="hover:underline"
                                        >
                                            {documento.titular}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {documento.tipo_label}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {documento.fecha_vencimiento ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={
                                                documento.vencido
                                                    ? 'inline-flex items-center rounded-none bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-700 ring-1 ring-red-600/20'
                                                    : 'inline-flex items-center rounded-none bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-600/20'
                                            }
                                        >
                                            {documento.vencido
                                                ? 'Vencido'
                                                : 'Por vencer'}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </section>
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
    children,
}: {
    titulo: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-5">
                <h2 className="text-sm font-semibold">{titulo}</h2>
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
