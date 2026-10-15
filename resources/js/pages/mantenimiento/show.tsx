import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Camera, ClipboardPlus, Gauge, Trash2, Wrench } from 'lucide-react';
import { useState } from 'react';
import { destroy as borrarLectura } from '@/actions/App/Http/Controllers/LecturaKilometrajeController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import {
    create as crearOrden,
    edit as editarOrden,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { ConfirmarBorradoDialog } from '@/components/confirmar-borrado-dialog';
import {
    bordeEstado,
    EstadoMantenimientoBadge,
    FaltanKm,
    formatearKm,
} from '@/components/mantenimiento/estado-mantenimiento';
import { RegistrarKmDialog } from '@/components/mantenimiento/registrar-km-dialog';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { usePermisos } from '@/hooks/use-permisos';
import {
    formatearFecha,
    formatearFechaHora,
    formatearPlaca,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    LecturaResumen,
    OrdenResumen,
    RepuestoAPreparar,
    ServicioUnidad,
    TractoKm,
    UnidadMantenimiento,
} from '@/types/mantenimiento';

type Props = {
    unidad: UnidadMantenimiento & {
        plan: { id: number; nombre: string } | null;
        servicios: ServicioUnidad[];
    };
    repuestos: RepuestoAPreparar[];
    ordenes: OrdenResumen[];
    lecturas: LecturaResumen[];
    tractos: TractoKm[];
};

/**
 * La ficha de mantenimiento de una unidad: dónde va cada servicio del plan,
 * qué hay que preparar para el próximo, y su historial de taller y de km.
 */
export default function MantenimientoShow({
    unidad,
    repuestos,
    ordenes,
    lecturas,
    tractos,
}: Props) {
    const { puede } = usePermisos();
    const puedeKm = puede('mantenimiento.registrar_km');
    const puedeOrdenes = puede('mantenimiento.gestionar');
    const [kmAbierto, setKmAbierto] = useState(false);
    const placa = formatearPlaca(unidad.placa);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: placa, href: mantenimiento.show(unidad.id).url },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Mantenimiento ${placa}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-wide">
                            {placa}
                        </h1>
                        <EstadoMantenimientoBadge
                            estado={unidad.estado}
                            label={unidad.estado_label}
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {[unidad.marca, unidad.modelo, unidad.anio]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <p className="text-sm">
                        Km actual:{' '}
                        <strong className="tabular-nums">
                            {formatearKm(unidad.km_actual)}
                        </strong>
                        {unidad.km_fecha && (
                            <span
                                className={cn(
                                    'ml-1 text-muted-foreground',
                                    unidad.km_desactualizado &&
                                        'font-medium text-amber-700 dark:text-amber-400',
                                )}
                            >
                                (
                                {unidad.km_desactualizado && 'desactualizado, '}
                                {formatearFecha(unidad.km_fecha)})
                            </span>
                        )}
                    </p>
                </div>

                <div className="flex gap-2 max-md:hidden">
                    {puedeOrdenes && (
                        <Button variant="outline" asChild>
                            <Link
                                href={crearOrden({
                                    query: { vehiculo: unidad.id },
                                })}
                            >
                                <ClipboardPlus className="size-4" />
                                Nueva orden
                            </Link>
                        </Button>
                    )}
                    {puedeKm && (
                        <RegistrarKmDialog
                            tractos={tractos}
                            vehiculoInicial={unidad.id}
                        />
                    )}
                </div>
            </div>

            {unidad.plan === null ? (
                <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                    La marca {unidad.marca ?? 'de esta unidad'} todavía no tiene
                    plan de mantenimiento. Su km y sus órdenes correctivas se
                    registran igual.
                </p>
            ) : (
                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                        {unidad.plan.nombre}
                    </h2>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {unidad.servicios.map((servicio) => (
                            <TarjetaServicio
                                key={servicio.id}
                                servicio={servicio}
                                esProximo={unidad.proximo?.id === servicio.id}
                            />
                        ))}
                    </div>
                </section>
            )}

            {unidad.proximo && repuestos.length > 0 && (
                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                        Para el {unidad.proximo.nombre}
                    </h2>
                    <ul className="divide-y rounded-xl border bg-card">
                        {repuestos.map((repuesto, indice) => (
                            <li
                                key={indice}
                                className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                            >
                                <span className="flex items-center gap-2">
                                    <span className="w-5 font-mono text-xs text-muted-foreground">
                                        {repuesto.servicio}
                                    </span>
                                    {repuesto.descripcion}
                                </span>
                                {repuesto.cantidad !== null && (
                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                        {repuesto.cantidad.toLocaleString(
                                            'es-PE',
                                        )}{' '}
                                        {repuesto.unidad}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                    Órdenes de trabajo
                </h2>
                {ordenes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Todavía no hay órdenes para esta unidad.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {ordenes.map((orden) => (
                            <li key={orden.id}>
                                <FilaOrden
                                    orden={orden}
                                    editable={puedeOrdenes}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                    Kilometrajes
                </h2>
                {lecturas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Todavía no se registró ningún km.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border bg-card">
                        {lecturas.map((lectura) => (
                            <li
                                key={lectura.id}
                                className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                            >
                                <div className="flex min-w-0 flex-col">
                                    <span className="font-medium tabular-nums">
                                        {formatearKm(lectura.kilometraje)}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {formatearFechaHora(lectura.leido_en)}
                                        {lectura.usuario &&
                                            ` · ${lectura.usuario}`}
                                        {lectura.observacion &&
                                            ` · ${lectura.observacion}`}
                                    </span>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    {lectura.foto_url && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <a
                                                href={lectura.foto_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label="Ver la foto del tablero"
                                            >
                                                <Camera className="size-4" />
                                            </a>
                                        </Button>
                                    )}
                                    {puedeKm && (
                                        <ConfirmarBorradoDialog
                                            url={borrarLectura(lectura.id).url}
                                            titulo="¿Eliminar este km?"
                                            descripcion={`Se borra el registro de ${formatearKm(lectura.kilometraje)}. Úsalo solo para corregir un error.`}
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Eliminar este km"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            }
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {puedeKm && (
                <>
                    <RegistrarKmDialog
                        tractos={tractos}
                        vehiculoInicial={unidad.id}
                        abierto={kmAbierto}
                        onAbiertoChange={setKmAbierto}
                    />
                    <AccionPrincipalMovil
                        onClick={() => setKmAbierto(true)}
                        icono={<Gauge />}
                    >
                        Registrar km
                    </AccionPrincipalMovil>
                </>
            )}
            {puedeOrdenes && (
                <Button variant="outline" className="h-12 md:hidden" asChild>
                    <Link href={crearOrden({ query: { vehiculo: unidad.id } })}>
                        <ClipboardPlus className="size-5" />
                        Nueva orden de trabajo
                    </Link>
                </Button>
            )}
        </div>
    );
}

function TarjetaServicio({
    servicio,
    esProximo,
}: {
    servicio: ServicioUnidad;
    esProximo: boolean;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1.5 rounded-xl border border-l-4 bg-card p-3',
                bordeEstado[servicio.estado],
                esProximo && 'ring-2 ring-primary/40',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="font-semibold">{servicio.nombre}</span>
                <span className="text-xs text-muted-foreground">
                    cada {formatearKm(servicio.intervalo_km)}
                    {servicio.intervalo_meses &&
                        ` o ${servicio.intervalo_meses} meses`}
                </span>
            </div>
            <FaltanKm
                faltan={servicio.faltan_km}
                estado={servicio.estado}
                className="text-sm"
            />
            <dl className="grid grid-cols-2 gap-x-2 text-xs text-muted-foreground">
                <dt>Último</dt>
                <dd className="text-right tabular-nums">
                    {servicio.ultimo_km === null
                        ? '—'
                        : `${formatearKm(servicio.ultimo_km)} · ${formatearFecha(servicio.ultima_fecha)}`}
                </dd>
                <dt>Próximo</dt>
                <dd className="text-right tabular-nums">
                    {formatearKm(servicio.proximo_km)}
                    {servicio.vence_el &&
                        ` · ${formatearFecha(servicio.vence_el)}`}
                </dd>
            </dl>
        </div>
    );
}

function FilaOrden({
    orden,
    editable,
}: {
    orden: OrdenResumen;
    editable: boolean;
}) {
    const contenido = (
        <div className="flex flex-col gap-1.5 rounded-xl border bg-card p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-2 font-medium">
                    <Wrench className="size-4 text-muted-foreground" />
                    {orden.servicio
                        ? `Servicio ${orden.servicio}`
                        : orden.tipo_label}
                </span>
                <StatusBadge
                    label={orden.estado_label}
                    tone={
                        orden.estado === 'finalizada'
                            ? 'success'
                            : orden.estado === 'en_proceso'
                              ? 'info'
                              : 'warning'
                    }
                />
            </div>
            <p className="line-clamp-2 text-muted-foreground">
                {orden.trabajo}
            </p>
            <p className="text-xs text-muted-foreground">
                {formatearFecha(orden.fecha)}
                {orden.kilometraje !== null &&
                    ` · ${formatearKm(orden.kilometraje)}`}
                {orden.tecnico && ` · ${orden.tecnico}`}
                {orden.fotos > 0 &&
                    ` · ${orden.fotos} ${orden.fotos === 1 ? 'foto' : 'fotos'}`}
            </p>
        </div>
    );

    return editable ? (
        <Link
            href={editarOrden(orden.id)}
            className="block rounded-xl hover:bg-accent/30"
        >
            {contenido}
        </Link>
    ) : (
        contenido
    );
}
