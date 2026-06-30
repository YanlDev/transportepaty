import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Car,
    FileWarning,
    Fuel,
    Gauge,
    Inbox,
    Users,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { pendientes as combustiblePendientes } from '@/actions/App/Http/Controllers/CargaCombustibleController';
import { index as mantenimientoIndex } from '@/actions/App/Http/Controllers/MantenimientoController';
import { ChartCombustibleMes } from '@/components/dashboard/chart-combustible-mes';
import { formatearFecha, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    ActividadEvento,
    AlertaDocumento,
    AlertaMantenimiento,
    DashboardData,
    FlotaEstado,
} from '@/types/dashboard';

const ESTADO_FLOTA_COLOR: Record<string, string> = {
    activo: 'bg-emerald-500',
    en_mantenimiento: 'bg-amber-500',
    inactivo: 'bg-slate-400',
    dado_de_baja: 'bg-rose-500',
};

const VENCIMIENTO_ESTILO = {
    vencido: {
        chip: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
        dot: 'bg-rose-500',
    },
    critico: {
        chip: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    por_vencer: {
        chip: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        dot: 'bg-sky-500',
    },
} as const;

export default function Dashboard({
    esGestor,
    kpis,
    flotaPorEstado,
    alertasDocumentos,
    alertasMantenimiento,
    combustibleSerie,
    actividad,
}: DashboardData) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        icon={Car}
                        label="Vehículos"
                        value={kpis.vehiculos_total}
                        hint={`${kpis.vehiculos_operativos} operativos · ${kpis.vehiculos_mantenimiento} en taller`}
                        color="text-emerald-600"
                    />
                    <KpiCard
                        icon={Fuel}
                        label="Combustible del mes"
                        value={formatearSoles(kpis.combustible_mes)}
                        hint="Gasto acumulado del mes en curso"
                        color="text-sky-600"
                    />
                    {esGestor ? (
                        <>
                            <KpiCard
                                icon={Users}
                                label="Conductores activos"
                                value={kpis.conductores_activos}
                                hint={`${kpis.sucursales} sucursales`}
                                color="text-violet-600"
                            />
                            <KpiCard
                                icon={Inbox}
                                label="Cargas por procesar"
                                value={kpis.cargas_pendientes}
                                hint={
                                    kpis.cargas_pendientes > 0
                                        ? 'Pendientes de revisión'
                                        : 'Todo al día'
                                }
                                color="text-amber-600"
                                href={combustiblePendientes().url}
                                alerta={kpis.cargas_pendientes > 0}
                            />
                        </>
                    ) : (
                        <KpiCard
                            icon={Building2}
                            label="Sucursales"
                            value={kpis.sucursales}
                            hint="Operativas"
                            color="text-violet-600"
                        />
                    )}
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        icon={FileWarning}
                        title="Documentos por vencer"
                        subtitle="SOAT, revisión técnica, seguros y licencias"
                    >
                        {alertasDocumentos.length === 0 ? (
                            <VacioPanel mensaje="No hay documentos próximos a vencer." />
                        ) : (
                            <ul className="flex flex-col divide-y divide-border">
                                {alertasDocumentos.map((alerta) => (
                                    <DocumentoFila
                                        key={alerta.id}
                                        alerta={alerta}
                                    />
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        icon={Wrench}
                        title="Mantenimiento pendiente"
                        subtitle="Servicios vencidos o próximos según el plan"
                    >
                        {alertasMantenimiento.length === 0 ? (
                            <VacioPanel mensaje="No hay mantenimientos pendientes." />
                        ) : (
                            <ul className="flex flex-col divide-y divide-border">
                                {alertasMantenimiento.map((alerta) => (
                                    <MantenimientoFila
                                        key={alerta.id}
                                        alerta={alerta}
                                    />
                                ))}
                            </ul>
                        )}
                    </Panel>
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    <Panel
                        className="lg:col-span-2"
                        icon={Fuel}
                        title="Gasto de combustible"
                        subtitle="Últimos 6 meses"
                    >
                        <ChartCombustibleMes serie={combustibleSerie} />
                    </Panel>

                    <Panel
                        icon={Gauge}
                        title="Estado de la flota"
                        subtitle="Distribución por estado"
                    >
                        {flotaPorEstado.length === 0 ? (
                            <VacioPanel mensaje="Sin vehículos registrados." />
                        ) : (
                            <FlotaEstadoLista
                                estados={flotaPorEstado}
                                total={kpis.vehiculos_total}
                            />
                        )}
                    </Panel>
                </section>

                <Panel
                    icon={AlertTriangle}
                    title="Actividad reciente"
                    subtitle="Últimos movimientos de la flota"
                >
                    {actividad.length === 0 ? (
                        <VacioPanel mensaje="Todavía no hay actividad registrada." />
                    ) : (
                        <ul className="flex flex-col divide-y divide-border">
                            {actividad.map((evento) => (
                                <ActividadFila
                                    key={evento.id}
                                    evento={evento}
                                />
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </>
    );
}

function KpiCard({
    icon: Icon,
    label,
    value,
    hint,
    color,
    href,
    alerta,
}: {
    icon: LucideIcon;
    label: string;
    value: string | number;
    hint: string;
    color: string;
    href?: string;
    alerta?: boolean;
}) {
    const contenido = (
        <div
            className={cn(
                'flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-5 transition',
                href && 'hover:border-foreground/20 hover:shadow-sm',
                alerta && 'border-amber-300 dark:border-amber-800',
            )}
        >
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-muted-foreground">
                    {label}
                </span>
                <span
                    className={cn(
                        'grid size-9 place-items-center rounded-lg bg-muted',
                        color,
                    )}
                >
                    <Icon className="size-5" />
                </span>
            </div>
            <div>
                <p className="text-2xl font-semibold tracking-tight text-foreground">
                    {value}
                </p>
                <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>
            </div>
        </div>
    );

    if (href) {
        return (
            <Link href={href} className="block">
                {contenido}
            </Link>
        );
    }

    return contenido;
}

function Panel({
    icon: Icon,
    title,
    subtitle,
    className,
    children,
}: {
    icon: LucideIcon;
    title: string;
    subtitle: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <section
            className={cn(
                'flex flex-col rounded-xl border border-border bg-card',
                className,
            )}
        >
            <header className="flex items-center gap-2.5 border-b border-border px-5 py-4">
                <Icon className="size-4 text-muted-foreground" />
                <div>
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                </div>
            </header>
            <div className="flex-1 px-5 py-2">{children}</div>
        </section>
    );
}

function VacioPanel({ mensaje }: { mensaje: string }) {
    return (
        <p className="py-8 text-center text-sm text-muted-foreground">
            {mensaje}
        </p>
    );
}

function DocumentoFila({ alerta }: { alerta: AlertaDocumento }) {
    const estilo = VENCIMIENTO_ESTILO[alerta.estado];

    return (
        <li className="flex items-center gap-3 py-3">
            <span className={cn('size-2 shrink-0 rounded-full', estilo.dot)} />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">
                    {alerta.tipo}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {alerta.referencia} · vence{' '}
                    {formatearFecha(alerta.fecha_vencimiento)}
                </p>
            </div>
            <span
                className={cn(
                    'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    estilo.chip,
                )}
            >
                {alerta.dias_restantes <= 0
                    ? `Venció hace ${Math.abs(alerta.dias_restantes)} d`
                    : `${alerta.dias_restantes} d`}
            </span>
        </li>
    );
}

function MantenimientoFila({ alerta }: { alerta: AlertaMantenimiento }) {
    const vencido = alerta.status === 'vencido';
    const detalle =
        alerta.restante_km !== null
            ? vencido
                ? `Excedido ${Math.abs(alerta.restante_km).toLocaleString('es-PE')} km`
                : `Faltan ${alerta.restante_km.toLocaleString('es-PE')} km`
            : alerta.restante_dias !== null
              ? vencido
                  ? `Vencido hace ${Math.abs(alerta.restante_dias)} d`
                  : `Faltan ${alerta.restante_dias} d`
              : '';

    return (
        <li className="py-3">
            <Link
                href={mantenimientoIndex(alerta.vehiculo_id).url}
                className="flex items-center gap-3"
            >
                <span
                    className={cn(
                        'size-2 shrink-0 rounded-full',
                        vencido ? 'bg-rose-500' : 'bg-amber-500',
                    )}
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground">
                        {alerta.servicio}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        <span className="font-mono">{alerta.placa}</span>
                        {detalle && ` · ${detalle}`}
                    </p>
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        vencido
                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                    )}
                >
                    {vencido ? 'Vencido' : 'Próximo'}
                </span>
            </Link>
        </li>
    );
}

function FlotaEstadoLista({
    estados,
    total,
}: {
    estados: FlotaEstado[];
    total: number;
}) {
    return (
        <div className="flex flex-col gap-4 py-2">
            <div className="flex h-2.5 w-full overflow-hidden rounded-full bg-muted">
                {estados.map((estado) => (
                    <div
                        key={estado.clave}
                        className={cn(
                            'h-full',
                            ESTADO_FLOTA_COLOR[estado.clave] ?? 'bg-slate-400',
                        )}
                        style={{
                            width: `${total > 0 ? (estado.cantidad / total) * 100 : 0}%`,
                        }}
                    />
                ))}
            </div>
            <ul className="flex flex-col gap-2.5">
                {estados.map((estado) => (
                    <li
                        key={estado.clave}
                        className="flex items-center justify-between text-sm"
                    >
                        <span className="flex items-center gap-2 text-muted-foreground">
                            <span
                                className={cn(
                                    'size-2.5 rounded-full',
                                    ESTADO_FLOTA_COLOR[estado.clave] ??
                                        'bg-slate-400',
                                )}
                            />
                            {estado.label}
                        </span>
                        <span className="font-semibold text-foreground">
                            {estado.cantidad}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function ActividadFila({ evento }: { evento: ActividadEvento }) {
    const esCombustible = evento.tipo === 'combustible';
    const Icon = esCombustible ? Fuel : Wrench;

    return (
        <li className="flex items-center gap-3 py-3">
            <span
                className={cn(
                    'grid size-9 shrink-0 place-items-center rounded-lg',
                    esCombustible
                        ? 'bg-sky-100 text-sky-600 dark:bg-sky-950'
                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950',
                )}
            >
                <Icon className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">
                    {esCombustible ? 'Carga de combustible' : 'Mantenimiento'}
                    {' · '}
                    <span className="font-mono text-muted-foreground">
                        {evento.placa}
                    </span>
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {evento.detalle}
                </p>
            </div>
            <span className="shrink-0 text-xs text-muted-foreground">
                {formatearFecha(evento.fecha.slice(0, 10))}
            </span>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
