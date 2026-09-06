import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Container,
    Eye,
    FileWarning,
    Target,
    Truck,
    Users,
} from 'lucide-react';
import { lazy, Suspense } from 'react';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import type {
    ConteoCarga,
    ConteoCliente,
    ViajesPorTipoCliente,
} from '@/components/dashboard/graficos-viajes';
import { Button } from '@/components/ui/button';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/**
 * Los gráficos arrastran recharts (~107 kB comprimido). Se cargan en su
 * propio chunk, después de que la página ya pintó las tarjetas y la meta del
 * mes: en un celular con datos móviles eso es la diferencia entre ver los
 * números al toque o esperar a que baje una librería que quizá ni se mire.
 */
const GraficosViajes = lazy(
    () => import('@/components/dashboard/graficos-viajes'),
);

type MetaConcentrado = {
    meta: number;
    realizados: number;
    faltantes: number;
    diasRestantes: number;
    proyeccion: number;
    ritmoNecesario: number | null;
};

type Documentos = {
    vigentes: number;
    vencidos: number;
    por_vencer: number;
    sin_fecha: number;
    total: number;
};

type Unidades = {
    operativas: number;
    no_programables: number;
    con_documentos_vencidos: number;
    total: number;
};

type UltimoViaje = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
    placa_tracto: string;
    placa_carreta: string | null;
    cliente: string;
    tipo_carga: string;
    tipo_carga_label: string;
    archivo_url: string | null;
};

type Props = {
    rango: { periodo: string; desde: string; hasta: string };
    resumen: {
        tractos: number;
        carretas: number;
        operativos: number;
        conductores: number;
        conductoresRegistrados: number;
        novedadesActivas: number;
        documentosVencidos: number;
    };
    metaConcentrado: MetaConcentrado;
    documentos: Documentos;
    unidades: Unidades;
    viajesPorCliente: ConteoCliente[];
    cargaMinsur: ConteoCarga[];
    viajesPorTipoCliente: ViajesPorTipoCliente;
    topCargas: { tipo: string; label: string; valor: number }[];
    ultimosViajes: UltimoViaje[];
};

const PERIODOS = [
    ['mes', 'Este mes'],
    ['trimestre', 'Últimos 3 meses'],
    ['anio', 'Este año'],
] as const;

export default function Dashboard({
    rango,
    resumen,
    metaConcentrado,
    documentos,
    unidades,
    viajesPorCliente,
    cargaMinsur,
    viajesPorTipoCliente,
    topCargas,
    ultimosViajes,
}: Props) {
    const cambiarPeriodo = (periodo: string) => {
        router.get(
            dashboard().url,
            { periodo },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Dashboard" />

            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Operaciones
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Resumen general de la flota, viajes y carga.
                    </p>
                </div>

                <div className="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                    <span className="rounded-md border border-border px-3 py-1.5 text-xs text-muted-foreground tabular-nums">
                        {formatearFecha(rango.desde)} —{' '}
                        {formatearFecha(rango.hasta)}
                    </span>

                    <div className="flex rounded-md border border-border p-0.5">
                        {PERIODOS.map(([valor, label]) => (
                            <button
                                key={valor}
                                type="button"
                                onClick={() => cambiarPeriodo(valor)}
                                className={cn(
                                    'rounded px-2.5 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                                    rango.periodo === valor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {/* Dos por fila en el celular: apiladas de a una obligaban a
                bajar cinco pantallas para llegar a la meta del mes. */}
            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
                <Tarjeta
                    label="Tractos"
                    valor={resumen.tractos}
                    detalle="Total en flota"
                    icon={<Truck className="size-5" />}
                    color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                />
                <Tarjeta
                    label="Carretas"
                    valor={resumen.carretas}
                    detalle="Total en flota"
                    icon={<Container className="size-5" />}
                    color="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                />
                <Tarjeta
                    label="Conductores activos"
                    valor={resumen.conductores}
                    detalle={`de ${resumen.conductoresRegistrados} registrados`}
                    icon={<Users className="size-5" />}
                    color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                />
                <Tarjeta
                    label="No programables hoy"
                    valor={resumen.novedadesActivas}
                    detalle="unidades con novedad vigente"
                    icon={<AlertTriangle className="size-5" />}
                    color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    tono={resumen.novedadesActivas > 0 ? 'ambar' : 'normal'}
                />
                <Tarjeta
                    label="Documentos vencidos"
                    valor={resumen.documentosVencidos}
                    detalle="de fierros y conductores"
                    icon={<FileWarning className="size-5" />}
                    color="bg-red-500/10 text-red-600 dark:text-red-400"
                    tono={resumen.documentosVencidos > 0 ? 'rojo' : 'normal'}
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <MetaConcentradoPanel meta={metaConcentrado} />
                <DocumentosPanel documentos={documentos} />
                <UnidadesPanel unidades={unidades} />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                <Suspense fallback={<GraficosCargando />}>
                    <GraficosViajes
                        cargaMinsur={cargaMinsur}
                        viajesPorCliente={viajesPorCliente}
                        viajesPorTipoCliente={viajesPorTipoCliente}
                    />
                </Suspense>
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.6fr)]">
                <TopLista
                    titulo="Top 5 clientes por viajes"
                    items={viajesPorCliente
                        .filter((fila) => !fila.es_otros)
                        .slice(0, 5)
                        .map((fila) => ({
                            clave: fila.cliente,
                            label: fila.cliente,
                            valor: fila.valor,
                        }))}
                    vacio="Sin viajes en este período."
                />

                <TopLista
                    titulo="Top 5 tipos de carga"
                    items={topCargas.map((fila) => ({
                        clave: fila.tipo,
                        label: fila.label,
                        valor: fila.valor,
                    }))}
                    vacio="Sin viajes en este período."
                />

                <UltimosViajes viajes={ultimosViajes} />
            </div>
        </div>
    );
}

/** Hueco de los gráficos mientras baja su chunk: evita el salto de layout. */
function GraficosCargando() {
    return (
        <>
            <div className="h-96 animate-pulse rounded-xl border border-border bg-card" />
            <div className="h-96 animate-pulse rounded-xl border border-border bg-card" />
        </>
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
    color,
    tono = 'normal',
}: {
    label: string;
    valor: number;
    detalle?: string;
    icon: React.ReactNode;
    color: string;
    tono?: 'normal' | 'ambar' | 'rojo';
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-4 sm:p-5">
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'grid size-10 shrink-0 place-items-center rounded-lg',
                        color,
                    )}
                    aria-hidden
                >
                    {icon}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-xs text-muted-foreground">
                        {label}
                    </p>
                    <p
                        className={cn(
                            'text-2xl font-semibold tabular-nums sm:text-3xl',
                            tonoValor[tono],
                        )}
                    >
                        {valor}
                    </p>
                    {detalle && (
                        <p className="truncate text-xs text-muted-foreground">
                            {detalle}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * El indicador principal del área: cuánto lleva el mes en curso de
 * concentrado contra la meta de 120, y a qué ritmo hay que cerrar los días
 * que quedan. Independiente del rango de arriba —siempre es el mes en curso,
 * porque el compromiso es mensual.
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
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Target className="size-4 text-muted-foreground" />
                    Meta de viajes — mes en curso
                </h2>
                <p className={cn('text-xs font-medium', tono)}>
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
                <span className="ml-auto text-sm text-muted-foreground tabular-nums">
                    {porcentaje}%
                </span>
            </div>

            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={cn('h-full rounded-full', colorBarra)}
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

function DocumentosPanel({ documentos }: { documentos: Documentos }) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <FileWarning className="size-4 text-muted-foreground" />
                    Estado de documentos
                </h2>
            </div>
            <div className="p-4">
                <Suspense fallback={<DonaCargando />}>
                    <DonaLazy
                        total={documentos.total}
                        etiquetaTotal="documentos"
                        datos={[
                            {
                                clave: 'vigentes',
                                label: 'Vigentes',
                                valor: documentos.vigentes,
                                color: 'var(--color-emerald-500)',
                            },
                            {
                                clave: 'vencidos',
                                label: 'Vencidos',
                                valor: documentos.vencidos,
                                color: 'var(--color-red-500)',
                            },
                            {
                                clave: 'por_vencer',
                                label: 'Por vencer (≤ 30 días)',
                                valor: documentos.por_vencer,
                                color: 'var(--color-amber-500)',
                            },
                            {
                                clave: 'sin_fecha',
                                label: 'Sin fecha',
                                valor: documentos.sin_fecha,
                                color: 'var(--color-zinc-400)',
                            },
                        ]}
                    />
                </Suspense>
            </div>
        </section>
    );
}

function UnidadesPanel({ unidades }: { unidades: Unidades }) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Truck className="size-4 text-muted-foreground" />
                    Unidades operativas
                </h2>
            </div>
            <div className="p-4">
                <Suspense fallback={<DonaCargando />}>
                    <DonaLazy
                        total={unidades.total}
                        etiquetaTotal="unidades"
                        datos={[
                            {
                                clave: 'operativas',
                                label: 'Operativas',
                                valor: unidades.operativas,
                                color: 'var(--color-emerald-500)',
                            },
                            {
                                clave: 'no_programables',
                                label: 'No programables',
                                valor: unidades.no_programables,
                                color: 'var(--color-amber-500)',
                            },
                            {
                                clave: 'con_documentos_vencidos',
                                label: 'Con documentos vencidos',
                                valor: unidades.con_documentos_vencidos,
                                color: 'var(--color-red-500)',
                            },
                        ]}
                    />
                </Suspense>
            </div>
        </section>
    );
}

const DonaLazy = lazy(async () => {
    const modulo = await import('@/components/dashboard/graficos-viajes');

    return { default: modulo.Dona };
});

function DonaCargando() {
    return <div className="h-[132px] animate-pulse rounded-lg bg-muted" />;
}

/** Un ranking corto con posiciones numeradas. */
function TopLista({
    titulo,
    items,
    vacio,
}: {
    titulo: string;
    items: { clave: string; label: string; valor: number }[];
    vacio: string;
}) {
    const colores = [
        'bg-red-500 text-white',
        'bg-amber-500 text-amber-950',
        'bg-zinc-400 text-zinc-950',
        'bg-violet-500 text-white',
        'bg-blue-500 text-white',
    ];

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="text-sm font-semibold">{titulo}</h2>
            </div>

            {items.length === 0 ? (
                <p className="p-6 text-center text-sm text-muted-foreground">
                    {vacio}
                </p>
            ) : (
                <ul className="divide-y">
                    {items.map((item, indice) => (
                        <li
                            key={item.clave}
                            className="flex items-center gap-3 p-3"
                        >
                            <span
                                className={cn(
                                    'grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums',
                                    colores[indice] ?? colores[4],
                                )}
                            >
                                {indice + 1}
                            </span>
                            <span
                                className="min-w-0 flex-1 truncate text-sm"
                                title={item.label}
                            >
                                {item.label}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {item.valor}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** Lo último que entró al sistema, sin importar el rango elegido arriba. */
function UltimosViajes({ viajes: lista }: { viajes: UltimoViaje[] }) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="text-sm font-semibold">
                    Últimos viajes registrados
                </h2>
                <Button asChild variant="ghost" size="sm">
                    <Link href={viajes.index()}>Ver todos</Link>
                </Button>
            </div>

            {lista.length === 0 ? (
                <p className="p-6 text-center text-sm text-muted-foreground">
                    Todavía no se ha registrado ningún viaje.
                </p>
            ) : (
                <>
                    <div className="flex flex-col divide-y sm:hidden">
                        {lista.map((viaje) => (
                            <div
                                key={viaje.id}
                                className="flex flex-col gap-2 p-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span className="text-sm font-semibold tabular-nums">
                                        {formatearFecha(viaje.fecha_traslado)}
                                    </span>
                                    <span className="shrink-0 font-mono text-xs text-muted-foreground">
                                        {formatearPlaca(viaje.placa_tracto)}
                                    </span>
                                </div>
                                <ClienteChip cliente={viaje.cliente} />
                                <TipoCargaBadge
                                    valor={viaje.tipo_carga}
                                    label={viaje.tipo_carga_label}
                                />
                            </div>
                        ))}
                    </div>

                    <div className="hidden overflow-hidden sm:block">
                        <table className="w-full table-fixed text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs text-muted-foreground">
                                    <th className="w-28 p-3 font-medium">
                                        Fecha
                                    </th>
                                    <th className="w-40 p-3 font-medium">
                                        Unidad
                                    </th>
                                    <th className="p-3 font-medium">Cliente</th>
                                    <th className="w-36 p-3 font-medium">
                                        Carga
                                    </th>
                                    <th className="w-12 p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {lista.map((viaje) => (
                                    <tr
                                        key={viaje.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 whitespace-nowrap tabular-nums">
                                            {formatearFecha(
                                                viaje.fecha_traslado,
                                            )}
                                        </td>
                                        <td className="p-3 font-mono text-xs whitespace-nowrap">
                                            {formatearPlaca(viaje.placa_tracto)}
                                            {viaje.placa_carreta &&
                                                ` / ${formatearPlaca(viaje.placa_carreta)}`}
                                        </td>
                                        <td className="max-w-0 p-3">
                                            <ClienteChip
                                                cliente={viaje.cliente}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <TipoCargaBadge
                                                valor={viaje.tipo_carga}
                                                label={viaje.tipo_carga_label}
                                            />
                                        </td>
                                        <td className="p-3 text-right">
                                            {viaje.archivo_url && (
                                                <a
                                                    href={viaje.archivo_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                    aria-label={`Ver GR ${viaje.numero_gr}`}
                                                >
                                                    <Eye className="size-4" />
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </section>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard().url }],
};
