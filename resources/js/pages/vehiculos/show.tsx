import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    Car,
    Clock,
    FileText,
    Fuel,
    Gauge,
    MapPin,
    Pencil,
    Route,
    Satellite,
    Trash2,
    User,
    Video,
    Wrench,
} from 'lucide-react';
import { index as combustible } from '@/actions/App/Http/Controllers/CargaCombustibleController';
import { camaraPage as camaras } from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import { index as mantenimiento } from '@/actions/App/Http/Controllers/MantenimientoController';
import { index as mapa } from '@/actions/App/Http/Controllers/MapaController';
import vehiculos, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { index as documentosIndex } from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
import { ChartRendimiento } from '@/components/combustible/chart-rendimiento';
import { RegistrarCargaDialog } from '@/components/combustible/registrar-carga-dialog';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { CalibrarOdometroDialog } from '@/components/vehiculos/calibrar-odometro-dialog';
import { DeleteVehiculoDialog } from '@/components/vehiculos/delete-vehiculo-dialog';
import { DocumentoItem } from '@/components/vehiculos/documento-item';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { FotosCarrusel } from '@/components/vehiculos/fotos-carrusel';
import { Dato, InfoCard } from '@/components/vehiculos/info-card';
import {
    formatearFecha,
    formatearFechaHora,
    formatearSoles,
} from '@/lib/format';
import { combustibleLabels, tipoLabels } from '@/types/fleet';
import type {
    EnumOption,
    Vehiculo,
    VehiculoDocumentoItem,
    VehiculoFotoItem,
} from '@/types/fleet';

type MantenimientoResumen = {
    id: number;
    fecha_realizado: string;
    odometro: number;
    costo_total: number | null;
    items: { id: number; nombre: string }[];
};

type ActividadItem = {
    id: string;
    tipo: 'mantenimiento' | 'combustible' | 'documento';
    titulo: string;
    detalle: string | null;
    fecha: string;
};

type Props = {
    vehiculo: Vehiculo;
    fotos: VehiculoFotoItem[];
    documentos: VehiculoDocumentoItem[];
    documentosTotal: number;
    mantenimientos: MantenimientoResumen[];
    mantenimientosTotal: number;
    actividadReciente: ActividadItem[];
    tiposDocumento: EnumOption[];
    posicionesFoto: EnumOption[];
    rendimientoCombustible: { fecha: string; rendimiento: number }[];
    puedeRegistrarCombustible: boolean;
};

export default function VehiculoShow({
    vehiculo,
    fotos,
    documentos,
    documentosTotal,
    mantenimientos,
    mantenimientosTotal,
    actividadReciente,
    tiposDocumento,
    posicionesFoto,
    rendimientoCombustible,
    puedeRegistrarCombustible,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos.index().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
        ],
    });

    const conductor = vehiculo.conductor
        ? `${vehiculo.conductor.nombres} ${vehiculo.conductor.apellidos}`
        : 'Sin asignar';

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <Head
                title={`${vehiculo.marca} ${vehiculo.modelo} · ${vehiculo.placa}`}
            />

            {/* Encabezado */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {vehiculo.marca} {vehiculo.modelo} {vehiculo.anio}
                        </h1>
                        <EstadoBadge estado={vehiculo.estado} />
                    </div>
                    <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span className="rounded-md bg-muted px-2 py-0.5 font-mono font-medium text-foreground">
                            {vehiculo.placa}
                        </span>
                        {vehiculo.numero_serie && (
                            <span>VIN: {vehiculo.numero_serie}</span>
                        )}
                    </div>
                </div>

                {puedeGestionar && (
                    <div className="flex items-center gap-2">
                        {vehiculo.imei && (
                            <CalibrarOdometroDialog
                                vehiculoId={vehiculo.id}
                                kilometrajeActual={vehiculo.kilometraje}
                                trigger={
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        title="Calibrar odómetro"
                                        aria-label="Calibrar odómetro"
                                    >
                                        <Gauge className="size-4" />
                                    </Button>
                                }
                            />
                        )}
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            title="Editar información"
                        >
                            <Link
                                href={edit(vehiculo.id)}
                                aria-label="Editar información"
                            >
                                <Pencil className="size-4" />
                            </Link>
                        </Button>
                        <DeleteVehiculoDialog
                            vehiculo={vehiculo}
                            trigger={
                                <Button
                                    variant="outline"
                                    size="icon"
                                    title="Eliminar"
                                    aria-label="Eliminar"
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            }
                        />
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-stretch">
                {/* Fotos (carrusel) */}
                <div className="h-full">
                    <FotosCarrusel
                        vehiculoId={vehiculo.id}
                        fotos={fotos}
                        posiciones={posicionesFoto}
                        puedeGestionar={puedeGestionar}
                    />
                </div>

                {/* Información general */}
                <div>
                    <InfoCard title="Información general">
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-4">
                            <Dato
                                icon={<Car className="size-4" />}
                                label="Marca"
                                value={vehiculo.marca}
                            />
                            <Dato label="Modelo" value={vehiculo.modelo} />
                            <Dato
                                icon={<Calendar className="size-4" />}
                                label="Año"
                                value={String(vehiculo.anio)}
                            />
                            <Dato label="Color" value={vehiculo.color ?? '—'} />
                            <Dato
                                icon={<Fuel className="size-4" />}
                                label="Combustible"
                                value={
                                    combustibleLabels[vehiculo.combustible] ??
                                    vehiculo.combustible
                                }
                            />
                            <Dato
                                label="Tipo"
                                value={
                                    tipoLabels[vehiculo.tipo] ?? vehiculo.tipo
                                }
                            />
                            <Dato
                                icon={<Gauge className="size-4" />}
                                label="Kilometraje"
                                value={`${vehiculo.kilometraje.toLocaleString('es-PE')} km`}
                            />
                            <Dato
                                label="N.° de motor"
                                value={vehiculo.numero_motor ?? '—'}
                            />
                            <Dato
                                icon={<Satellite className="size-4" />}
                                label="GPS (IMEI)"
                                value={vehiculo.imei ?? 'Sin dispositivo'}
                            />
                            <Dato
                                label="Adquisición"
                                value={formatearFecha(
                                    vehiculo.fecha_adquisicion,
                                )}
                            />
                        </dl>
                    </InfoCard>
                </div>

                {/* Estado del vehículo */}
                <div>
                    <InfoCard title="Estado del vehículo">
                        <dl className="space-y-4">
                            <Dato
                                icon={<User className="size-4" />}
                                label="Conductor asignado"
                                value={conductor}
                                full
                            />
                            <Dato
                                icon={<Building2 className="size-4" />}
                                label="Sucursal"
                                value={vehiculo.sucursal?.nombre ?? '—'}
                                full
                            />
                            <Dato
                                icon={<MapPin className="size-4" />}
                                label="Ciudad"
                                value={vehiculo.sucursal?.ciudad ?? '—'}
                                full
                            />
                            <Dato
                                icon={<Clock className="size-4" />}
                                label="Última actualización"
                                value={formatearFechaHora(vehiculo.updated_at)}
                                full
                            />
                        </dl>

                        {vehiculo.imei && (
                            <div className="mt-5 space-y-2 border-t border-border pt-4">
                                <Button
                                    asChild
                                    className="w-full bg-emerald-800 hover:bg-emerald-900"
                                >
                                    <Link
                                        href={mapa({
                                            query: { vehiculo: vehiculo.id },
                                        })}
                                    >
                                        <MapPin className="size-4" />
                                        Ver ubicación en tiempo real
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link
                                        href={mapa({
                                            query: { recorrido: vehiculo.id },
                                        })}
                                    >
                                        <Route className="size-4" />
                                        Recorridos
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href={camaras(vehiculo.id)}>
                                        <Video className="size-4" />
                                        Cámaras en vivo
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </InfoCard>
                </div>
            </div>

            {/* Documentación · Resumen de uso · Mantenimientos */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
                {/* Documentación */}
                <section className="rounded-xl border border-border bg-card p-5">
                    <div className="mb-4 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-foreground">
                            Documentación
                        </h2>
                        {puedeGestionar && (
                            <AgregarDocumentoDialog
                                vehiculoId={vehiculo.id}
                                tipos={tiposDocumento}
                            />
                        )}
                    </div>

                    {documentos.length === 0 ? (
                        <EmptyState
                            icon={<FileText className="size-6" />}
                            text="Aún no hay documentos. Sube los escaneos del vehículo."
                        />
                    ) : (
                        <div className="flex flex-col gap-2.5">
                            {documentos.map((documento) => (
                                <DocumentoItem
                                    key={documento.id}
                                    documento={documento}
                                    vehiculoId={vehiculo.id}
                                    puedeGestionar={puedeGestionar}
                                />
                            ))}

                            {documentosTotal > documentos.length && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-1.5 w-full"
                                >
                                    <Link href={documentosIndex(vehiculo.id)}>
                                        Ver todos los documentos (
                                        {documentosTotal})
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </section>

                {/* Rendimiento de combustible: registrar en la cabecera, clic
                    en el gráfico para ver el historial completo. */}
                <ChartRendimiento
                    data={rendimientoCombustible}
                    href={combustible(vehiculo.id).url}
                    action={
                        puedeRegistrarCombustible ? (
                            <RegistrarCargaDialog
                                vehiculoId={vehiculo.id}
                                directo={puedeGestionar}
                                odometroSugerido={vehiculo.kilometraje}
                                compacto
                            />
                        ) : null
                    }
                />

                {/* Mantenimientos */}
                <section className="rounded-xl border border-border bg-card p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-foreground">
                            Mantenimientos
                        </h2>
                    </div>

                    {mantenimientos.length === 0 ? (
                        <EmptyState
                            icon={<Wrench className="size-6" />}
                            text="Aún no se registraron mantenimientos."
                        />
                    ) : (
                        <ul className="flex flex-col gap-2.5">
                            {mantenimientos.map((m) => (
                                <li
                                    key={m.id}
                                    className="rounded-lg border border-border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-foreground">
                                            {formatearFecha(m.fecha_realizado)}
                                        </span>
                                        <span className="font-mono text-[11px] text-muted-foreground">
                                            {m.odometro.toLocaleString('es-PE')}{' '}
                                            km
                                        </span>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                        {m.items
                                            .map((item) => item.nombre)
                                            .join(', ') || 'Servicio general'}
                                    </p>
                                    {m.costo_total !== null && (
                                        <p className="mt-1 text-xs font-medium text-foreground">
                                            {formatearSoles(m.costo_total)}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <Button
                        asChild
                        variant={
                            mantenimientos.length === 0 ? 'default' : 'outline'
                        }
                        className={
                            mantenimientos.length === 0
                                ? 'mt-4 w-full bg-emerald-800 hover:bg-emerald-900'
                                : 'mt-3 w-full'
                        }
                    >
                        <Link href={mantenimiento(vehiculo.id)}>
                            <Wrench className="size-4" />
                            {mantenimientos.length === 0
                                ? 'Registrar mantenimiento'
                                : `Ver mantenimientos (${mantenimientosTotal})`}
                        </Link>
                    </Button>
                </section>
            </div>

            {/* Observaciones */}
            {vehiculo.observaciones && (
                <InfoCard title="Observaciones">
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {vehiculo.observaciones}
                    </p>
                </InfoCard>
            )}

            {/* Historial de actividad reciente */}
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-foreground">
                        Historial de actividad reciente
                    </h2>
                </div>

                {actividadReciente.length === 0 ? (
                    <EmptyState
                        icon={<Clock className="size-6" />}
                        text="Aún no hay actividad registrada para este vehículo."
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {actividadReciente.map((actividad) => (
                            <ActividadCard
                                key={actividad.id}
                                actividad={actividad}
                            />
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

const ACTIVIDAD_ESTILOS: Record<
    ActividadItem['tipo'],
    { icon: React.ReactNode; className: string }
> = {
    mantenimiento: {
        icon: <Wrench className="size-4" />,
        className: 'bg-amber-100 text-amber-700',
    },
    combustible: {
        icon: <Fuel className="size-4" />,
        className: 'bg-sky-100 text-sky-700',
    },
    documento: {
        icon: <FileText className="size-4" />,
        className: 'bg-emerald-100 text-emerald-700',
    },
};

function ActividadCard({ actividad }: { actividad: ActividadItem }) {
    const estilo = ACTIVIDAD_ESTILOS[actividad.tipo];

    return (
        <li className="flex items-start gap-3 rounded-lg border border-border p-3">
            <span
                className={`grid size-9 shrink-0 place-items-center rounded-lg ${estilo.className}`}
            >
                {estilo.icon}
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">
                    {actividad.titulo}
                </p>
                {actividad.detalle && (
                    <p className="truncate text-xs text-muted-foreground">
                        {actividad.detalle}
                    </p>
                )}
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                    {formatearFechaHora(actividad.fecha)}
                </p>
            </div>
        </li>
    );
}
