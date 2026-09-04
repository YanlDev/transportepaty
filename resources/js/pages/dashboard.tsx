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

type ConteoCarga = { tipo: string; label: string; valor: number };
type ConteoCliente = { cliente: string; valor: number };

type MetaConcentrado = {
    meta: number;
    realizados: number;
    faltantes: number;
    diasRestantes: number;
    proyeccion: number;
    ritmoNecesario: number | null;
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
    metaConcentrado: MetaConcentrado;
    filtroMes: string | null;
    mesesDisponibles: string[];
    cargaMinsur: ConteoCarga[];
    viajesPorCliente: ConteoCliente[];
};

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
    metaConcentrado,
    filtroMes,
    mesesDisponibles,
    cargaMinsur,
    viajesPorCliente,
}: Props) {
    const cambiarMes = (mes: string) => {
        router.get(
            dashboard().url,
            { mes },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hayCargaMinsur = cargaMinsur.some((carga) => carga.valor > 0);
    const hayViajesPorCliente = viajesPorCliente.length > 0;
    const alturaViajesPorCliente = Math.max(192, viajesPorCliente.length * 32);

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

            <MetaConcentradoPanel meta={metaConcentrado} />

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

/**
 * El indicador principal del área: cuánto lleva el mes en curso de
 * concentrado contra la meta de 120, y a qué ritmo hay que cerrar los días
 * que quedan. Independiente del selector de mes de abajo —siempre es el mes
 * en curso, no el que se esté mirando en el gráfico histórico.
 */
function MetaConcentradoPanel({ meta }: { meta: MetaConcentrado }) {
    const porcentaje = Math.min(
        100,
        Math.round((meta.realizados / meta.meta) * 100),
    );
    const vaBienEncaminado = meta.proyeccion >= meta.meta;
    const vaAjustado = !vaBienEncaminado && meta.proyeccion >= meta.meta * 0.9;

    const tono = vaBienEncaminado
        ? 'text-emerald-700 dark:text-emerald-500'
        : vaAjustado
          ? 'text-amber-700 dark:text-amber-500'
          : 'text-red-700 dark:text-red-500';

    const colorBarra = vaBienEncaminado
        ? 'bg-emerald-500'
        : vaAjustado
          ? 'bg-amber-500'
          : 'bg-red-500';

    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-sm font-semibold">
                    Meta de concentrado — mes en curso
                </h2>
                <p className={`text-xs font-medium ${tono}`}>
                    Proyección: {meta.proyeccion} viajes
                </p>
            </div>

            <div className="mt-3 flex items-baseline gap-2">
                <span className="text-3xl font-semibold tabular-nums">
                    {meta.realizados}
                </span>
                <span className="text-sm text-muted-foreground">
                    / {meta.meta} viajes
                </span>
            </div>

            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-full rounded-full ${colorBarra}`}
                    style={{ width: `${porcentaje}%` }}
                />
            </div>

            <p className="mt-3 text-xs text-muted-foreground">
                Faltan {meta.faltantes} viajes · {meta.diasRestantes} días
                restantes
                {meta.ritmoNecesario !== null &&
                    ` · ritmo necesario: ${meta.ritmoNecesario}/día`}
            </p>
        </section>
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
