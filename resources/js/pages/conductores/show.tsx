import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Eye,
    FileText,
    IdCard,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Route as RouteIcon,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import conductores, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/ConductorController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { CalendarioAsistenciaAnual } from '@/components/asistencia/calendario-anual';
import { AgregarDocumentoConductorDialog } from '@/components/conductores/agregar-documento-dialog';
import { DocumentoRanuraConductor } from '@/components/conductores/documento-ranura';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { estadoConfig } from '@/lib/asistencia';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    AsistenciaCalendarioAnual,
    AsistenciaCalendarioMes,
    Conductor,
    ConductorEstadisticas,
    ConductorViajeItem,
    EnumOption,
    EstadoAsistencia,
    EstadoDocumental,
    RanuraDocumental,
} from '@/types/fleet';

type Props = {
    conductor: Conductor;
    documentacion: EstadoDocumental;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
    asistencia: AsistenciaCalendarioAnual | null;
    estadisticas: ConductorEstadisticas;
    viajes: ConductorViajeItem[];
};

/**
 * El expediente del conductor en una sola pantalla: quién es a la izquierda,
 * sus números y su actividad al centro, y sus papeles a la derecha. Sustituye
 * a las pestañas: acá casi todo lo que se consulta a diario se ve sin hacer
 * un solo clic, y lo que sí necesita profundidad —el año completo de
 * asistencia— se despliega sobre el mismo lugar.
 */
export default function ConductorShow({
    conductor,
    documentacion,
    ranuras,
    tiposDocumento,
    asistencia,
    estadisticas,
    viajes: viajesRecientes,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    const obligatorias = ranuras.filter((ranura) => ranura.obligatorio);
    const sueltas = ranuras.filter((ranura) => !ranura.obligatorio);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Conductores', href: conductores.index().url },
            { title: conductor.nombre_completo, href: show(conductor.id).url },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={conductor.nombre_completo} />

            <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[280px_minmax(0,1fr)_320px]">
                <Identidad
                    conductor={conductor}
                    puedeGestionar={puedeGestionar}
                />

                <div className="flex min-w-0 flex-col gap-4">
                    <Indicadores estadisticas={estadisticas} />

                    <ViajesRecientes
                        viajes={viajesRecientes}
                        nombreConductor={conductor.nombre_completo}
                    />

                    {asistencia && (
                        <Asistencia
                            conductorId={conductor.id}
                            asistencia={asistencia}
                        />
                    )}
                </div>

                <div className="flex flex-col gap-4">
                    <LicenciaCard conductor={conductor} />

                    <section className="rounded-xl border border-border bg-card">
                        <div className="flex items-center justify-between gap-2 border-b p-4">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <FileText className="size-4 text-muted-foreground" />
                                Documentos
                            </h2>
                            {puedeGestionar && (
                                <AgregarDocumentoConductorDialog
                                    conductorId={conductor.id}
                                    tipos={tiposDocumento}
                                />
                            )}
                        </div>

                        <div className="flex flex-col gap-2.5 p-4">
                            {obligatorias.map((ranura) => (
                                <DocumentoRanuraConductor
                                    key={ranura.tipo}
                                    ranura={ranura}
                                    conductorId={conductor.id}
                                    tipos={tiposDocumento}
                                    puedeGestionar={puedeGestionar}
                                />
                            ))}

                            {sueltas.length > 0 && (
                                <>
                                    <p className="mt-2 text-xs font-medium text-muted-foreground">
                                        Otros papeles
                                    </p>
                                    {sueltas.map((ranura) => (
                                        <DocumentoRanuraConductor
                                            key={
                                                ranura.documento?.id ??
                                                ranura.tipo
                                            }
                                            ranura={ranura}
                                            conductorId={conductor.id}
                                            tipos={tiposDocumento}
                                            puedeGestionar={puedeGestionar}
                                        />
                                    ))}
                                </>
                            )}

                            {documentacion.faltantes.length > 0 && (
                                <p className="text-xs text-red-700 dark:text-red-400">
                                    Falta cargar:{' '}
                                    {documentacion.faltantes.join(', ')}.
                                </p>
                            )}
                        </div>
                    </section>

                    <ProximosViajes />
                </div>
            </div>
        </div>
    );
}

/**
 * Reservado para cuando exista asignación de viajes: hoy la app registra lo
 * que ya pasó (las GR emitidas), no lo que se va a hacer, así que la tarjeta
 * queda como marcador del lugar que ocuparía esa lista. El botón está
 * deshabilitado a propósito —no hay a dónde ir todavía.
 */
function ProximosViajes() {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    Próximos viajes
                </h2>
                <Button
                    variant="outline"
                    size="sm"
                    disabled
                    title="La asignación de viajes todavía no está implementada"
                >
                    <Plus className="size-4" />
                    Asignar viaje
                </Button>
            </div>

            <div className="flex flex-col items-center gap-2 p-8 text-center">
                <CalendarDays className="size-10 text-muted-foreground/30" />
                <p className="text-sm font-medium">
                    No hay viajes próximos asignados
                </p>
                <p className="text-xs text-muted-foreground">
                    Cuando se asignen viajes, aparecerán aquí.
                </p>
            </div>
        </section>
    );
}

/** Iniciales para el avatar: primera letra del nombre y del apellido. */
function iniciales(conductor: Conductor): string {
    return `${conductor.nombres.charAt(0)}${conductor.apellidos.charAt(0)}`.toUpperCase();
}

/** Los años cumplidos a hoy, para leer la edad sin sacar la cuenta. */
function edad(fechaNacimiento: string): number {
    const nacimiento = new Date(`${fechaNacimiento}T00:00:00`);
    const hoy = new Date();
    const anios = hoy.getFullYear() - nacimiento.getFullYear();
    const cumplioEsteAnio =
        hoy.getMonth() > nacimiento.getMonth() ||
        (hoy.getMonth() === nacimiento.getMonth() &&
            hoy.getDate() >= nacimiento.getDate());

    return cumplioEsteAnio ? anios : anios - 1;
}

/** Quién es: la columna que no cambia mientras se navega el resto. */
function Identidad({
    conductor,
    puedeGestionar,
}: {
    conductor: Conductor;
    puedeGestionar: boolean;
}) {
    return (
        <section className="flex flex-col gap-5 rounded-xl border border-border bg-card p-5 lg:sticky lg:top-4 lg:self-start">
            <div className="flex flex-col items-center gap-3 text-center lg:items-start lg:text-left">
                <div className="relative">
                    <div className="grid size-20 place-items-center rounded-full bg-primary text-2xl font-semibold text-primary-foreground">
                        {iniciales(conductor)}
                    </div>
                    <span
                        className={cn(
                            'absolute right-1 bottom-1 size-4 rounded-full ring-2 ring-card',
                            conductor.activo ? 'bg-emerald-500' : 'bg-zinc-400',
                        )}
                        aria-hidden
                    />
                </div>

                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        {conductor.nombre_completo}
                    </h1>
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        DNI {conductor.documento}
                        <StatusBadge
                            label={conductor.activo ? 'Activo' : 'Inactivo'}
                            tone={conductor.activo ? 'success' : 'neutral'}
                            dot={false}
                        />
                    </p>
                </div>

                {puedeGestionar && (
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="w-full"
                    >
                        <Link href={edit(conductor.id)}>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </Button>
                )}
            </div>

            {!conductor.activo && (
                <div className="rounded-lg bg-muted/50 p-3 text-xs">
                    <p className="font-medium">
                        Baja
                        {conductor.fecha_baja &&
                            ` · ${formatearFecha(conductor.fecha_baja)}`}
                    </p>
                    {conductor.motivo_baja && (
                        <p className="mt-1 text-muted-foreground">
                            {conductor.motivo_baja}
                        </p>
                    )}
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 border-t pt-4">
                <div>
                    <p className="text-xs text-muted-foreground">Licencia</p>
                    <p className="mt-0.5 font-mono text-sm">
                        {conductor.licencia ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Categoría</p>
                    <p className="mt-0.5 text-sm">
                        {conductor.categoria_licencia ?? '—'}
                    </p>
                </div>
            </div>

            <div className="border-t pt-4">
                <h2 className="mb-3 text-sm font-semibold">
                    Información personal
                </h2>
                <ul className="flex flex-col gap-2.5 text-sm">
                    <DatoPersonal icono={Phone}>
                        {conductor.telefono ? (
                            <a
                                href={`tel:${conductor.telefono}`}
                                className="tabular-nums hover:underline"
                            >
                                {conductor.telefono}
                            </a>
                        ) : (
                            '—'
                        )}
                    </DatoPersonal>
                    <DatoPersonal icono={Mail}>
                        {conductor.email ?? '—'}
                    </DatoPersonal>
                    <DatoPersonal icono={MapPin}>
                        {conductor.procedencia ?? '—'}
                    </DatoPersonal>
                    <DatoPersonal icono={CalendarDays}>
                        {conductor.fecha_nacimiento ? (
                            <>
                                {formatearFecha(conductor.fecha_nacimiento)}
                                <span className="ml-1.5 text-muted-foreground">
                                    ({edad(conductor.fecha_nacimiento)} años)
                                </span>
                            </>
                        ) : (
                            '—'
                        )}
                    </DatoPersonal>
                    <DatoPersonal icono={ShieldCheck}>
                        Revalidación{' '}
                        {conductor.licencia_vence
                            ? formatearFecha(conductor.licencia_vence)
                            : '—'}
                    </DatoPersonal>
                </ul>
            </div>
        </section>
    );
}

function DatoPersonal({
    icono: Icono,
    children,
}: {
    icono: typeof Phone;
    children: React.ReactNode;
}) {
    return (
        <li className="flex items-center gap-2.5">
            <Icono className="size-4 shrink-0 text-muted-foreground" />
            <span className="min-w-0 truncate">{children}</span>
        </li>
    );
}

/** Las cuatro tarjetas de arriba: cómo viene el conductor de un vistazo. */
function Indicadores({
    estadisticas,
}: {
    estadisticas: ConductorEstadisticas;
}) {
    const documentosCompletos =
        estadisticas.documentos_vigentes === estadisticas.documentos_totales;

    return (
        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <Indicador
                icono={<RouteIcon className="size-5" />}
                label="Viajes totales"
                valor={estadisticas.viajes_totales}
                detalle={
                    estadisticas.ultimo_viaje
                        ? `Último: ${formatearFecha(estadisticas.ultimo_viaje)}`
                        : 'Sin viajes'
                }
                color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
            />
            {estadisticas.dias_trabajados_mes !== null && (
                <Indicador
                    icono={<CalendarCheck className="size-5" />}
                    label="Días trabajados"
                    valor={estadisticas.dias_trabajados_mes}
                    detalle="Este mes"
                    color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                />
            )}
            {estadisticas.dias_descanso_mes !== null && (
                <Indicador
                    icono={<CalendarDays className="size-5" />}
                    label="Días de descanso"
                    valor={estadisticas.dias_descanso_mes}
                    detalle={
                        estadisticas.faltas_mes
                            ? `Este mes · ${estadisticas.faltas_mes} falta${estadisticas.faltas_mes === 1 ? '' : 's'}`
                            : 'Este mes'
                    }
                    color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                />
            )}
            <Indicador
                icono={<FileText className="size-5" />}
                label="Documentos"
                valor={`${estadisticas.documentos_vigentes} / ${estadisticas.documentos_totales}`}
                detalle={
                    documentosCompletos ? 'Vigentes' : 'Requieren atención'
                }
                color={
                    documentosCompletos
                        ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400'
                        : 'bg-red-500/10 text-red-600 dark:text-red-400'
                }
            />
        </div>
    );
}

function Indicador({
    icono,
    label,
    valor,
    detalle,
    color,
}: {
    icono: React.ReactNode;
    label: string;
    valor: number | string;
    detalle: string;
    color: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-xl border border-border bg-card p-4">
            <span
                className={cn(
                    'grid size-10 shrink-0 place-items-center rounded-lg',
                    color,
                )}
            >
                {icono}
            </span>
            <div className="min-w-0">
                <p className="truncate text-xs text-muted-foreground">
                    {label}
                </p>
                <p className="text-xl font-semibold tabular-nums">{valor}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {detalle}
                </p>
            </div>
        </div>
    );
}

/**
 * Los últimos viajes que manejó. El historial completo no vive acá: «Ver
 * todos» va al listado general ya filtrado por su nombre, que es donde están
 * los filtros y el detalle de cada GR.
 */
function ViajesRecientes({
    viajes: lista,
    nombreConductor,
}: {
    viajes: ConductorViajeItem[];
    nombreConductor: string;
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <RouteIcon className="size-4 text-muted-foreground" />
                    Viajes recientes
                </h2>
                {lista.length > 0 && (
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={
                                viajes.index({
                                    query: { buscar: nombreConductor },
                                }).url
                            }
                        >
                            Ver todos
                        </Link>
                    </Button>
                )}
            </div>

            {lista.length === 0 ? (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    Este conductor todavía no tiene viajes registrados.
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
                                        {viaje.placa_carreta &&
                                            ` / ${formatearPlaca(viaje.placa_carreta)}`}
                                    </span>
                                </div>
                                <ClienteChip cliente={viaje.cliente} />
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <TipoCargaBadge
                                        valor={viaje.tipo_carga}
                                        label={viaje.tipo_carga_label}
                                    />
                                    <GuiaRemision viaje={viaje} />
                                </div>
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
                                    {/* La única columna elástica: absorbe el
                                        ancho sobrante y trunca los nombres
                                        largos en vez de estirar la tabla. */}
                                    <th className="p-3 font-medium">Cliente</th>
                                    <th className="w-36 p-3 font-medium">
                                        Carga
                                    </th>
                                    <th className="w-36 p-3 font-medium">GR</th>
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
                                        <td className="p-3 whitespace-nowrap">
                                            <GuiaRemision viaje={viaje} />
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

/**
 * El N° de GR del viaje, enlazado a su PDF cuando quedó adjunto. Es el dato
 * con el que se rastrea un viaje contra SUNAT, así que va en el historial
 * aunque el resto de columnas se hayan recortado.
 */
function GuiaRemision({ viaje }: { viaje: ConductorViajeItem }) {
    if (!viaje.archivo_url) {
        return <span className="font-mono text-xs">{viaje.numero_gr}</span>;
    }

    return (
        <a
            href={viaje.archivo_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1.5 font-mono text-xs hover:underline"
        >
            {viaje.numero_gr}
            <Eye className="size-3.5 shrink-0 text-muted-foreground" />
        </a>
    );
}

/**
 * La asistencia del conductor: un mes a la vez, con su resumen al lado. El
 * año completo —doce calendarios, para marcar de corrido— se despliega abajo
 * solo cuando hace falta, en vez de ocupar la ficha entera de entrada.
 */
function Asistencia({
    conductorId,
    asistencia,
}: {
    conductorId: number;
    asistencia: AsistenciaCalendarioAnual;
}) {
    // Arranca en el mes en curso si el año mostrado es el actual; si se está
    // mirando otro año, en enero.
    const hoy = new Date();
    const [indiceMes, setIndiceMes] = useState(() =>
        hoy.getFullYear() === asistencia.anio ? hoy.getMonth() : 0,
    );
    const [verAnio, setVerAnio] = useState(false);

    const mes = asistencia.calendarios[indiceMes];

    if (!mes) {
        return null;
    }

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <CalendarCheck className="size-4 text-muted-foreground" />
                    Asistencia
                </h2>
            </div>

            <div className="grid gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                <MesCompacto
                    mes={mes}
                    puedeRetroceder={indiceMes > 0}
                    puedeAvanzar={indiceMes < asistencia.calendarios.length - 1}
                    onRetroceder={() => setIndiceMes((i) => i - 1)}
                    onAvanzar={() => setIndiceMes((i) => i + 1)}
                />

                <div className="flex flex-col gap-4">
                    <ResumenMes mes={mes} />

                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={() => setVerAnio((abierto) => !abierto)}
                    >
                        {verAnio
                            ? 'Ocultar el año'
                            : 'Ver detalle de asistencia'}
                    </Button>
                </div>
            </div>

            {verAnio && (
                <div className="border-t p-4">
                    <CalendarioAsistenciaAnual
                        conductorId={conductorId}
                        anio={asistencia.anio}
                        calendarios={asistencia.calendarios}
                        urlPagina={show(conductorId).url}
                    />
                </div>
            )}
        </section>
    );
}

/** «agosto 2026» a partir del primer día del mes (Y-m-d). */
function formatearMes(mes: string): string {
    return new Date(`${mes}T00:00:00`).toLocaleDateString('es-PE', {
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Un mes en chico, solo para leer: cada día marcado se pinta como un círculo
 * lleno del color de su estado, y el día de hoy va anillado. Marcar y
 * corregir se hace en la vista del año o en el rooster, donde las celdas son
 * grandes y están pensadas para eso.
 */
function MesCompacto({
    mes,
    puedeRetroceder,
    puedeAvanzar,
    onRetroceder,
    onAvanzar,
}: {
    mes: AsistenciaCalendarioMes;
    puedeRetroceder: boolean;
    puedeAvanzar: boolean;
    onRetroceder: () => void;
    onAvanzar: () => void;
}) {
    const hoy = new Date().toISOString().slice(0, 10);
    const nombresDias = mes.dias.slice(0, 7).map((dia) => dia.dia_semana);

    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    onClick={onRetroceder}
                    disabled={!puedeRetroceder}
                    aria-label="Mes anterior"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <p className="text-sm font-medium text-primary capitalize">
                    {formatearMes(mes.mes)}
                </p>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    onClick={onAvanzar}
                    disabled={!puedeAvanzar}
                    aria-label="Mes siguiente"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>

            <div className="grid grid-cols-7 justify-items-center gap-y-1.5">
                {nombresDias.map((nombre, indice) => (
                    <span
                        key={indice}
                        className="pb-1 text-[11px] font-medium text-muted-foreground"
                    >
                        {nombre}
                    </span>
                ))}

                {mes.dias.map((dia) => {
                    const marca = mes.marcas[dia.fecha];
                    const info = marca ? estadoConfig[marca.estado] : null;
                    const esHoy = dia.fecha === hoy;

                    return (
                        <span
                            key={dia.fecha}
                            title={`${dia.numero} — ${info ? info.label : 'Sin marcar'}`}
                            className={cn(
                                'grid size-8 place-items-center rounded-full text-xs font-medium tabular-nums',
                                dia.es_relleno && 'text-muted-foreground/30',
                                !dia.es_relleno &&
                                    !info &&
                                    'text-muted-foreground',
                                !dia.es_relleno && info && info.solido,
                                // Hoy sin marcar se pinta igual, en el color de
                                // la app, para ubicarse en el mes de un vistazo.
                                esHoy &&
                                    !info &&
                                    'bg-primary text-primary-foreground',
                                esHoy &&
                                    info &&
                                    'ring-2 ring-primary ring-offset-1 ring-offset-card',
                            )}
                        >
                            {dia.numero}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}

/** Cuántos días de cada estado tiene el mes que se está mirando. */
function ResumenMes({ mes }: { mes: AsistenciaCalendarioMes }) {
    const conteos = Object.values(mes.marcas).reduce<
        Record<EstadoAsistencia, number>
    >(
        (acumulado, marca) => {
            acumulado[marca.estado] += 1;

            return acumulado;
        },
        { asistencia: 0, falta: 0, vacaciones: 0, descanso: 0 },
    );

    return (
        <div className="divide-y rounded-lg border border-border">
            {(Object.keys(estadoConfig) as EstadoAsistencia[]).map((estado) => (
                <div
                    key={estado}
                    className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm"
                >
                    <span className="flex items-center gap-2.5">
                        <span
                            className={cn(
                                'size-2.5 shrink-0 rounded-full',
                                estadoConfig[estado].punto,
                            )}
                            aria-hidden
                        />
                        {estadoConfig[estado].labelResumen}
                    </span>
                    <span className="font-semibold tabular-nums">
                        {conteos[estado]}
                    </span>
                </div>
            ))}

            <div className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                <span className="flex items-center gap-2.5 text-muted-foreground">
                    <span
                        className="size-2.5 shrink-0 rounded-full bg-muted-foreground/40"
                        aria-hidden
                    />
                    Balance del mes
                </span>
                <span className="font-semibold tabular-nums">
                    {mes.dias_debidos > 0 && '+'}
                    {mes.dias_debidos}
                </span>
            </div>
        </div>
    );
}

/** La licencia de conducir, con su vigencia calculada por el semáforo. */
function LicenciaCard({ conductor }: { conductor: Conductor }) {
    const vencida = conductor.licencia_vence
        ? new Date(`${conductor.licencia_vence}T00:00:00`) < new Date()
        : false;

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <IdCard className="size-4 text-muted-foreground" />
                    Licencia de conducir
                </h2>
                {conductor.licencia_vence && (
                    <StatusBadge
                        label={vencida ? 'Vencida' : 'Vigente'}
                        tone={vencida ? 'danger' : 'success'}
                        dot={false}
                    />
                )}
            </div>

            <div className="grid grid-cols-2 gap-4 p-4">
                <div>
                    <p className="text-xs text-muted-foreground">
                        N.º de licencia
                    </p>
                    <p className="mt-0.5 font-mono text-sm">
                        {conductor.licencia ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Categoría</p>
                    <p className="mt-0.5 text-sm">
                        {conductor.categoria_licencia ?? '—'}
                    </p>
                </div>
                <div className="col-span-2">
                    <p className="text-xs text-muted-foreground">
                        Revalidación
                    </p>
                    <p
                        className={cn(
                            'mt-0.5 text-sm tabular-nums',
                            vencida && 'text-red-700 dark:text-red-400',
                        )}
                    >
                        {conductor.licencia_vence
                            ? formatearFecha(conductor.licencia_vence)
                            : '—'}
                    </p>
                </div>
            </div>
        </section>
    );
}
