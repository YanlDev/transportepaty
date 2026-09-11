import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Container,
    FileWarning,
    Truck,
    Users,
} from 'lucide-react';
import { lazy, Suspense } from 'react';
import type {
    ConteoCarga,
    ConteoCliente,
    ViajesPorTipoCliente,
} from '@/components/dashboard/graficos-viajes';
import { MetaConcentradoPanel } from '@/components/dashboard/meta-concentrado-panel';
import {
    DocumentosPanel,
    UnidadesPanel,
} from '@/components/dashboard/paneles-flota';
import { TopLista } from '@/components/dashboard/top-lista';
import { UltimosViajes } from '@/components/dashboard/ultimos-viajes';
import { Indicador } from '@/components/ui/indicador';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    Documentos,
    MetaConcentrado,
    ResumenFlota,
    Unidades,
} from '@/types/dashboard';
import type { ConductorViajeItem } from '@/types/fleet';

/**
 * Los gráficos arrastran recharts (~107 kB comprimido). Se cargan en su
 * propio chunk, después de que la página ya pintó las tarjetas y la meta del
 * mes: en un celular con datos móviles eso es la diferencia entre ver los
 * números al toque o esperar a que baje una librería que quizá ni se mire.
 */
const GraficosViajes = lazy(
    () => import('@/components/dashboard/graficos-viajes'),
);

type Props = {
    rango: { periodo: string; desde: string; hasta: string };
    resumen: ResumenFlota;
    metaConcentrado: MetaConcentrado;
    documentos: Documentos;
    unidades: Unidades;
    viajesPorCliente: ConteoCliente[];
    cargaMinsur: ConteoCarga[];
    viajesPorTipoCliente: ViajesPorTipoCliente;
    topCargas: { tipo: string; label: string; valor: number }[];
    ultimosViajes: ConductorViajeItem[];
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
                <Indicador
                    tamano="grande"
                    label="Tractos"
                    valor={resumen.tractos}
                    pie="Total en flota"
                    icono={<Truck className="size-5" />}
                    color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                />
                <Indicador
                    tamano="grande"
                    label="Carretas"
                    valor={resumen.carretas}
                    pie="Total en flota"
                    icono={<Container className="size-5" />}
                    color="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                />
                <Indicador
                    tamano="grande"
                    label="Conductores activos"
                    valor={resumen.conductores}
                    pie={`de ${resumen.conductoresRegistrados} registrados`}
                    icono={<Users className="size-5" />}
                    color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                />
                <Indicador
                    tamano="grande"
                    label="No programables hoy"
                    valor={resumen.novedadesActivas}
                    pie="unidades con novedad vigente"
                    icono={<AlertTriangle className="size-5" />}
                    color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    tono={resumen.novedadesActivas > 0 ? 'ambar' : 'normal'}
                />
                <Indicador
                    tamano="grande"
                    label="Documentos vencidos"
                    valor={resumen.documentosVencidos}
                    pie="de fierros y conductores"
                    icono={<FileWarning className="size-5" />}
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
            <div className="h-[320px] animate-pulse rounded-xl border border-border bg-card" />
            <div className="h-[320px] animate-pulse rounded-xl border border-border bg-card" />
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard().url }],
};
