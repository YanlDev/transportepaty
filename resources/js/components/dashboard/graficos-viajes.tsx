import { useState } from 'react';
import {
    Bar,
    BarChart,
    Cell,
    LabelList,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { useIsMobile } from '@/hooks/use-mobile';
import { clienteColor } from '@/lib/cliente-color';
import { cn } from '@/lib/utils';

export type ConteoCarga = {
    tipo: string;
    label: string;
    valor: number;
    porcentaje: number;
};

export type ConteoCliente = {
    cliente: string;
    valor: number;
    porcentaje: number;
    es_minsur: boolean;
    es_otros: boolean;
};

export type ViajesPorTipoCliente = {
    minsur: number;
    particulares: number;
    total: number;
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

const sinConfig = {} satisfies ChartConfig;

type Filtro = 'todos' | 'minsur' | 'particulares';

/**
 * Los gráficos del tablero, en su propio chunk: recharts pesa más de 100 kB
 * comprimido y no tiene por qué descargarse antes de que las tarjetas de
 * arriba —que son lo que se mira desde el celular— estén en pantalla.
 */
export default function GraficosViajes({
    cargaMinsur,
    viajesPorCliente,
    viajesPorTipoCliente,
}: {
    cargaMinsur: ConteoCarga[];
    viajesPorCliente: ConteoCliente[];
    viajesPorTipoCliente: ViajesPorTipoCliente;
}) {
    return (
        <>
            <ViajesPorClientePanel viajes={viajesPorCliente} />

            <div className="flex flex-col gap-4">
                <CargaMinsurPanel carga={cargaMinsur} />
                <TipoClientePanel reparto={viajesPorTipoCliente} />
            </div>
        </>
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
            <div className="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                <h2 className="text-sm font-semibold">{titulo}</h2>
                {extra}
            </div>
            <div className="p-4">{children}</div>
        </section>
    );
}

function EstadoVacio({ texto }: { texto: string }) {
    return (
        <div className="flex h-48 items-center justify-center text-center text-sm text-muted-foreground">
            {texto}
        </div>
    );
}

/**
 * Quién mueve más carga en el período. El filtro de arriba separa el cliente
 * principal del resto, que es la pregunta real: cuánto de lo que se hizo fue
 * para Minsur y cuánto fue relleno de retorno.
 */
function ViajesPorClientePanel({ viajes }: { viajes: ConteoCliente[] }) {
    const [filtro, setFiltro] = useState<Filtro>('todos');
    const esMovil = useIsMobile();

    const filtrados = viajes.filter((fila) => {
        if (filtro === 'minsur') {
            return fila.es_minsur;
        }

        if (filtro === 'particulares') {
            return !fila.es_minsur;
        }

        return true;
    });

    const altura = Math.max(200, filtrados.length * 34);

    return (
        <Panel
            titulo="Viajes por cliente"
            extra={
                <div className="flex rounded-md border border-border p-0.5">
                    {(
                        [
                            ['todos', 'Todos'],
                            ['minsur', 'Minsur'],
                            ['particulares', 'Particulares'],
                        ] as const
                    ).map(([valor, label]) => (
                        <button
                            key={valor}
                            type="button"
                            onClick={() => setFiltro(valor)}
                            className={cn(
                                'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                filtro === valor
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            }
        >
            {filtrados.length === 0 ? (
                <EstadoVacio texto="Sin viajes registrados en este período." />
            ) : (
                <div className="flex gap-3">
                    <ChartContainer
                        config={sinConfig}
                        className="min-w-0 flex-1"
                        style={{ height: altura }}
                    >
                        <BarChart
                            data={filtrados}
                            layout="vertical"
                            margin={{ left: 4, right: 32 }}
                        >
                            <XAxis type="number" hide />
                            <YAxis
                                dataKey="cliente"
                                type="category"
                                tickLine={false}
                                axisLine={false}
                                width={esMovil ? 110 : 240}
                                tick={{ fontSize: esMovil ? 10 : 11 }}
                            />
                            <ChartTooltip
                                cursor={{ fill: 'var(--muted)' }}
                                content={<ChartTooltipContent hideLabel />}
                            />
                            <Bar dataKey="valor" radius={2} barSize={16}>
                                {filtrados.map((fila) => (
                                    <Cell
                                        key={fila.cliente}
                                        fill={
                                            fila.es_otros
                                                ? 'var(--color-zinc-400)'
                                                : clienteColor(fila.cliente)
                                                      .chart
                                        }
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

                    {/* Los porcentajes van en su propia columna y no dentro de
                        la barra: pegados al valor se solapan cuando la barra
                        es corta. */}
                    <div
                        className="hidden w-12 shrink-0 flex-col justify-around py-1 text-right text-xs text-muted-foreground tabular-nums sm:flex"
                        style={{ height: altura }}
                    >
                        {filtrados.map((fila) => (
                            <span key={fila.cliente}>{fila.porcentaje}%</span>
                        ))}
                    </div>
                </div>
            )}
        </Panel>
    );
}

/** La mezcla de carga del cliente principal. */
function CargaMinsurPanel({ carga }: { carga: ConteoCarga[] }) {
    const esMovil = useIsMobile();
    const conValor = carga.filter((fila) => fila.valor > 0);

    return (
        <Panel titulo="Clasificación de carga — Minsur">
            {conValor.length === 0 ? (
                <EstadoVacio texto="Sin viajes de Minsur en este período." />
            ) : (
                <ChartContainer
                    config={cargaMinsurConfig}
                    className="w-full"
                    style={{ height: Math.max(160, conValor.length * 38) }}
                >
                    <BarChart
                        data={conValor}
                        layout="vertical"
                        margin={{ left: 4, right: 40 }}
                    >
                        <XAxis type="number" hide />
                        <YAxis
                            dataKey="label"
                            type="category"
                            tickLine={false}
                            axisLine={false}
                            width={esMovil ? 74 : 92}
                            tick={{ fontSize: esMovil ? 10 : 11 }}
                        />
                        <ChartTooltip
                            cursor={{ fill: 'var(--muted)' }}
                            content={<ChartTooltipContent hideLabel />}
                        />
                        <Bar dataKey="valor" radius={2} barSize={18}>
                            {conValor.map((fila) => (
                                <Cell
                                    key={fila.tipo}
                                    fill={`var(--color-${fila.tipo})`}
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
            )}
        </Panel>
    );
}

/** Minsur contra el resto, en una dona con su leyenda al costado. */
function TipoClientePanel({ reparto }: { reparto: ViajesPorTipoCliente }) {
    const datos = [
        {
            clave: 'minsur',
            label: 'Minsur',
            valor: reparto.minsur,
            color: 'var(--color-blue-500)',
        },
        {
            clave: 'particulares',
            label: 'Particulares',
            valor: reparto.particulares,
            color: 'var(--color-emerald-500)',
        },
    ];

    return (
        <Panel titulo="Viajes por tipo de cliente">
            {reparto.total === 0 ? (
                <EstadoVacio texto="Sin viajes registrados en este período." />
            ) : (
                <Dona
                    datos={datos}
                    total={reparto.total}
                    etiquetaTotal="viajes"
                />
            )}
        </Panel>
    );
}

export type PorcionDona = {
    clave: string;
    label: string;
    valor: number;
    color: string;
};

/**
 * Una dona con su leyenda al lado: el total va en el centro y cada porción
 * lista su valor y el porcentaje que representa. Se usa para los repartos del
 * tablero —documentos, unidades, tipo de cliente— que son siempre «un total
 * partido en pocas categorías».
 */
export function Dona({
    datos,
    total,
    etiquetaTotal,
}: {
    datos: PorcionDona[];
    total: number;
    etiquetaTotal: string;
}) {
    return (
        <div className="flex flex-wrap items-center gap-4">
            <div className="relative shrink-0">
                <ChartContainer config={sinConfig} className="size-[132px]">
                    <PieChart>
                        <ChartTooltip
                            content={<ChartTooltipContent hideLabel />}
                        />
                        <Pie
                            data={datos}
                            dataKey="valor"
                            nameKey="label"
                            innerRadius={44}
                            outerRadius={64}
                            paddingAngle={2}
                            strokeWidth={0}
                        >
                            {datos.map((porcion) => (
                                <Cell
                                    key={porcion.clave}
                                    fill={porcion.color}
                                />
                            ))}
                        </Pie>
                    </PieChart>
                </ChartContainer>

                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-xl font-semibold tabular-nums">
                        {total}
                    </span>
                    <span className="text-[10px] text-muted-foreground">
                        {etiquetaTotal}
                    </span>
                </div>
            </div>

            <ul className="min-w-0 flex-1 space-y-1.5">
                {datos.map((porcion) => (
                    <li
                        key={porcion.clave}
                        className="flex items-center gap-2 text-sm"
                    >
                        <span
                            className="size-2.5 shrink-0 rounded-full"
                            style={{ backgroundColor: porcion.color }}
                            aria-hidden
                        />
                        <span className="min-w-0 flex-1 truncate text-muted-foreground">
                            {porcion.label}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {porcion.valor}
                        </span>
                        <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">
                            {total > 0
                                ? Math.round((porcion.valor / total) * 100)
                                : 0}
                            %
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
