import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Eye,
    MessageCircle,
    Package,
    Pencil,
    Route as RouteIcon,
    Star,
    TrendingDown,
    TrendingUp,
    Truck,
} from 'lucide-react';
import clientes, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/ClienteController';
import { show as mostrarConductor } from '@/actions/App/Http/Controllers/ConductorController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { enlaceWhatsapp } from '@/lib/contacto';
import { formatearFecha, formatearPeso, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Cliente, ClienteViajeItem } from '@/types/fleet';

type Estadisticas = {
    viajes_totales: number;
    viajes_mes: number;
    variacion_mes: number | null;
    ultimo_viaje: string | null;
    primer_viaje: string | null;
    tipos_carga: number;
    carga_principal: string | null;
    ruta_frecuente: {
        origen: string;
        destino: string;
        viajes: number;
    } | null;
};

type Props = {
    cliente: Cliente;
    estadisticas: Estadisticas;
    viajes: ClienteViajeItem[];
};

/** «Hace 3 días» a partir de una fecha Y-m-d. */
function haceCuanto(fecha: string): string {
    const dias = Math.round(
        (Date.now() - new Date(`${fecha}T00:00:00`).getTime()) / 86_400_000,
    );

    if (dias <= 0) {
        return 'Hoy';
    }

    if (dias === 1) {
        return 'Ayer';
    }

    return `Hace ${dias} días`;
}

export default function ClienteShow({
    cliente,
    estadisticas,
    viajes: ultimosViajes,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const whatsapp = enlaceWhatsapp(cliente.telefono);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: clientes.index().url },
            { title: cliente.alias, href: show(cliente.id).url },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1500px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={cliente.alias} />

            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {cliente.razon_social}
                        </h1>
                        <StatusBadge
                            label={cliente.activo ? 'Activo' : 'Inactivo'}
                            tone={cliente.activo ? 'success' : 'neutral'}
                        />
                        {cliente.recurrente && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                                <Star className="size-3" />
                                Recurrente
                            </span>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        <span className="font-mono">RUC {cliente.ruc}</span>
                        {cliente.nombre_comercial && (
                            <span> · {cliente.nombre_comercial}</span>
                        )}
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    {whatsapp && (
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={whatsapp}
                                target="_blank"
                                rel="noreferrer"
                                className="text-emerald-700 dark:text-emerald-400"
                            >
                                <MessageCircle className="size-4" />
                                WhatsApp
                            </a>
                        </Button>
                    )}
                    {puedeGestionar && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={edit(cliente.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Indicador
                    icono={<Truck className="size-5" />}
                    label="Viajes realizados"
                    valor={estadisticas.viajes_totales}
                    color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                    pie={
                        <Variacion
                            variacion={estadisticas.variacion_mes}
                            viajesMes={estadisticas.viajes_mes}
                        />
                    }
                />
                <Indicador
                    icono={<CalendarDays className="size-5" />}
                    label="Último viaje"
                    valor={
                        estadisticas.ultimo_viaje
                            ? formatearFecha(estadisticas.ultimo_viaje)
                            : '—'
                    }
                    color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    pie={
                        estadisticas.ultimo_viaje
                            ? haceCuanto(estadisticas.ultimo_viaje)
                            : 'Sin viajes'
                    }
                />
                <Indicador
                    icono={<Package className="size-5" />}
                    label="Tipos de carga"
                    valor={estadisticas.tipos_carga}
                    color="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                    pie={estadisticas.carga_principal ?? 'Sin viajes'}
                />
                <Indicador
                    icono={<RouteIcon className="size-5" />}
                    label="Ruta más frecuente"
                    valor={
                        estadisticas.ruta_frecuente ? (
                            <span className="flex items-center gap-1.5 text-base">
                                <span className="truncate">
                                    {estadisticas.ruta_frecuente.origen}
                                </span>
                                <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="truncate">
                                    {estadisticas.ruta_frecuente.destino}
                                </span>
                            </span>
                        ) : (
                            '—'
                        )
                    }
                    color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    pie={
                        estadisticas.ruta_frecuente
                            ? `${estadisticas.ruta_frecuente.viajes} viajes`
                            : 'Sin viajes'
                    }
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <section className="min-w-0 rounded-xl border border-border bg-card">
                    <div className="flex items-center justify-between gap-2 border-b p-4">
                        <h2 className="flex items-center gap-2 text-sm font-semibold">
                            <Truck className="size-4 text-muted-foreground" />
                            Últimos viajes
                        </h2>
                        {ultimosViajes.length > 0 && (
                            <Button asChild variant="ghost" size="sm">
                                <Link
                                    href={
                                        viajes.index({
                                            query: {
                                                cliente: cliente.razon_social,
                                            },
                                        }).url
                                    }
                                >
                                    Ver todos
                                </Link>
                            </Button>
                        )}
                    </div>

                    {ultimosViajes.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            Este cliente todavía no tiene viajes registrados.
                        </p>
                    ) : (
                        <>
                            <div className="flex flex-col divide-y lg:hidden">
                                {ultimosViajes.map((viaje) => (
                                    <div
                                        key={viaje.id}
                                        className="flex flex-col gap-2 p-3"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="text-sm font-semibold tabular-nums">
                                                {formatearFecha(
                                                    viaje.fecha_traslado,
                                                )}
                                            </span>
                                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                {formatearPeso(
                                                    viaje.peso,
                                                    viaje.unidad_peso,
                                                )}
                                            </span>
                                        </div>
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <span className="truncate">
                                                {viaje.origen_ciudad}
                                            </span>
                                            <ArrowRight className="size-3 shrink-0" />
                                            <span className="truncate">
                                                {viaje.destino_ciudad}
                                            </span>
                                        </p>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {formatearPlaca(
                                                    viaje.placa_tracto,
                                                )}
                                            </span>
                                            <TipoCargaBadge
                                                valor={viaje.tipo_carga}
                                                label={viaje.tipo_carga_label}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="hidden overflow-hidden lg:block">
                                <table className="w-full table-fixed text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                            <th className="w-24 p-3 font-medium">
                                                Fecha
                                            </th>
                                            <th className="w-32 p-3 font-medium">
                                                N.º GR
                                            </th>
                                            <th className="w-36 p-3 font-medium">
                                                Unidad
                                            </th>
                                            <th className="p-3 font-medium">
                                                Conductor
                                            </th>
                                            <th className="w-40 p-3 font-medium">
                                                Ruta
                                            </th>
                                            <th className="w-32 p-3 font-medium">
                                                Carga
                                            </th>
                                            <th className="w-24 p-3 text-right font-medium">
                                                Peso
                                            </th>
                                            <th className="w-12 p-3" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ultimosViajes.map((viaje) => (
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
                                                    {viaje.numero_gr}
                                                </td>
                                                <td className="p-3 font-mono text-xs whitespace-nowrap">
                                                    {formatearPlaca(
                                                        viaje.placa_tracto,
                                                    )}
                                                    {viaje.placa_carreta &&
                                                        ` / ${formatearPlaca(viaje.placa_carreta)}`}
                                                </td>
                                                <td className="max-w-0 truncate p-3">
                                                    {viaje.conductor_id ? (
                                                        <Link
                                                            href={mostrarConductor(
                                                                viaje.conductor_id,
                                                            )}
                                                            className="hover:underline"
                                                            title={
                                                                viaje.conductor_nombre
                                                            }
                                                        >
                                                            {
                                                                viaje.conductor_nombre
                                                            }
                                                        </Link>
                                                    ) : (
                                                        <span
                                                            className="text-amber-700 dark:text-amber-500"
                                                            title={
                                                                viaje.conductor_nombre
                                                            }
                                                        >
                                                            {
                                                                viaje.conductor_nombre
                                                            }
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                        <span className="truncate">
                                                            {
                                                                viaje.origen_ciudad
                                                            }
                                                        </span>
                                                        <ArrowRight className="size-3 shrink-0" />
                                                        <span className="truncate">
                                                            {
                                                                viaje.destino_ciudad
                                                            }
                                                        </span>
                                                    </span>
                                                </td>
                                                <td className="p-3">
                                                    <TipoCargaBadge
                                                        valor={viaje.tipo_carga}
                                                        label={
                                                            viaje.tipo_carga_label
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                                    {formatearPeso(
                                                        viaje.peso,
                                                        viaje.unidad_peso,
                                                    )}
                                                </td>
                                                <td className="p-3 text-right">
                                                    {viaje.archivo_url && (
                                                        <a
                                                            href={
                                                                viaje.archivo_url
                                                            }
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

                <section className="rounded-xl border border-border bg-card">
                    <div className="border-b p-4">
                        <h2 className="text-sm font-semibold">
                            Información del cliente
                        </h2>
                    </div>

                    <dl className="divide-y">
                        <Dato label="Razón social">{cliente.razon_social}</Dato>
                        <Dato label="RUC">
                            <span className="font-mono">{cliente.ruc}</span>
                        </Dato>
                        <Dato label="Nombre comercial">
                            {cliente.nombre_comercial ?? '—'}
                        </Dato>
                        <Dato label="Dirección">
                            {cliente.direccion ?? '—'}
                        </Dato>
                        <Dato label="Contacto">{cliente.contacto ?? '—'}</Dato>
                        <Dato label="Teléfono">
                            {cliente.telefono ? (
                                <span className="flex items-center justify-end gap-2">
                                    <a
                                        href={`tel:${cliente.telefono}`}
                                        className="tabular-nums hover:underline"
                                    >
                                        {cliente.telefono}
                                    </a>
                                    {whatsapp && (
                                        <a
                                            href={whatsapp}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="grid size-7 shrink-0 place-items-center rounded-md text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400"
                                            aria-label={`Escribir por WhatsApp a ${cliente.alias}`}
                                            title="Escribir por WhatsApp"
                                        >
                                            <MessageCircle className="size-4" />
                                        </a>
                                    )}
                                </span>
                            ) : (
                                '—'
                            )}
                        </Dato>
                        <Dato label="Correo">{cliente.email ?? '—'}</Dato>
                        <Dato label="Estado">
                            <StatusBadge
                                label={cliente.activo ? 'Activo' : 'Inactivo'}
                                tone={cliente.activo ? 'success' : 'neutral'}
                            />
                        </Dato>
                        {cliente.notas && (
                            <div className="p-3">
                                <dt className="text-xs text-muted-foreground">
                                    Observaciones
                                </dt>
                                <dd className="mt-1 text-sm whitespace-pre-line">
                                    {cliente.notas}
                                </dd>
                            </div>
                        )}
                    </dl>
                </section>
            </div>
        </div>
    );
}

function Dato({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3 p-3 text-sm">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 truncate text-right">{children}</dd>
        </div>
    );
}

/**
 * Cómo viene el mes contra el anterior. Es la única tendencia del sistema que
 * se puede calcular de verdad: los viajes tienen fecha, así que el mes pasado
 * se puede volver a contar cuando haga falta.
 */
function Variacion({
    variacion,
    viajesMes,
}: {
    variacion: number | null;
    viajesMes: number;
}) {
    if (variacion === null) {
        return <>{viajesMes} este mes</>;
    }

    const subio = variacion >= 0;

    return (
        <span className="flex items-center gap-1">
            <span
                className={cn(
                    'inline-flex items-center gap-0.5 font-medium',
                    subio
                        ? 'text-emerald-700 dark:text-emerald-500'
                        : 'text-red-700 dark:text-red-500',
                )}
            >
                {subio ? (
                    <TrendingUp className="size-3" />
                ) : (
                    <TrendingDown className="size-3" />
                )}
                {subio && '+'}
                {variacion}%
            </span>
            vs. mes anterior
        </span>
    );
}

function Indicador({
    icono,
    label,
    valor,
    pie,
    color,
}: {
    icono: React.ReactNode;
    label: string;
    valor: React.ReactNode;
    pie: React.ReactNode;
    color: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border border-border bg-card p-4">
            <span
                className={cn(
                    'grid size-10 shrink-0 place-items-center rounded-lg',
                    color,
                )}
                aria-hidden
            >
                {icono}
            </span>
            <div className="min-w-0">
                <p className="truncate text-xs text-muted-foreground">
                    {label}
                </p>
                <div className="truncate text-xl font-semibold tabular-nums">
                    {valor}
                </div>
                <div className="truncate text-xs text-muted-foreground">
                    {pie}
                </div>
            </div>
        </div>
    );
}
