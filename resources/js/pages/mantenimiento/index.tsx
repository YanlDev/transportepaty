import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ClipboardPlus, Gauge, Wrench } from 'lucide-react';
import { useState } from 'react';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { create as crearOrden } from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { EmptyState } from '@/components/empty-state';
import { ListadoResponsivo } from '@/components/listado-responsivo';
import {
    bordeEstado,
    EstadoMantenimientoBadge,
    FaltanKm,
    formatearKm,
} from '@/components/mantenimiento/estado-mantenimiento';
import { RegistrarKmDialog } from '@/components/mantenimiento/registrar-km-dialog';
import { Button } from '@/components/ui/button';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    EstadoMantenimiento,
    ResumenMantenimiento,
    TractoKm,
    UnidadMantenimiento,
} from '@/types/mantenimiento';

type Props = {
    unidades: UnidadMantenimiento[];
    resumen: ResumenMantenimiento;
    /** Las marcas que ya tienen plan. */
    planes: string[];
    tractos: TractoKm[];
};

const fondoResumen: Record<EstadoMantenimiento, string> = {
    vencido: 'text-red-700 dark:text-red-400',
    proximo: 'text-amber-700 dark:text-amber-400',
    al_dia: 'text-emerald-700 dark:text-emerald-400',
    sin_datos: 'text-muted-foreground',
};

/**
 * El semáforo de mantenimiento: qué unidad necesita taller y cuál viene
 * después. Viene ordenado del servidor —lo vencido primero, luego lo que
 * está por tocar— para que lo urgente quede arriba sin filtrar nada.
 */
export default function MantenimientoIndex({
    unidades,
    resumen,
    planes,
    tractos,
}: Props) {
    const { puede } = usePermisos();
    const puedeKm = puede('mantenimiento.registrar_km');
    const puedeOrdenes = puede('mantenimiento.gestionar');
    const [kmAbierto, setKmAbierto] = useState(false);
    const [filtro, setFiltro] = useState<EstadoMantenimiento | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
        ],
    });

    const visibles =
        filtro === null
            ? unidades
            : unidades.filter((unidad) => unidad.estado === filtro);

    const irA = (unidad: UnidadMantenimiento) =>
        router.visit(mantenimiento.show(unidad.id));

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Mantenimiento" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    Plan preventivo de {planes.join(', ') || 'ninguna marca'} ·{' '}
                    {unidades.length}{' '}
                    {unidades.length === 1 ? 'unidad' : 'unidades'}
                </p>

                <div className="flex gap-2 max-md:hidden">
                    {puedeOrdenes && (
                        <Button variant="outline" asChild>
                            <Link href={crearOrden()}>
                                <ClipboardPlus className="size-4" />
                                Nueva orden
                            </Link>
                        </Button>
                    )}
                    {puedeKm && <RegistrarKmDialog tractos={tractos} />}
                </div>
            </div>

            {/* El resumen también filtra: tocar «Vencido» deja solo esas. */}
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {resumen.map((item) => (
                    <button
                        key={item.estado}
                        type="button"
                        onClick={() =>
                            setFiltro(
                                filtro === item.estado ? null : item.estado,
                            )
                        }
                        aria-pressed={filtro === item.estado}
                        className={cn(
                            'flex flex-col items-start rounded-xl border bg-card p-3 text-left transition-colors hover:bg-accent/50',
                            filtro === item.estado &&
                                'ring-2 ring-primary ring-offset-1 ring-offset-background',
                        )}
                    >
                        <span
                            className={cn(
                                'text-2xl font-semibold tabular-nums',
                                fondoResumen[item.estado],
                            )}
                        >
                            {item.cantidad}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {item.label}
                        </span>
                    </button>
                ))}
            </div>

            <ListadoResponsivo
                items={visibles}
                clave={(unidad) => unidad.id}
                vacio={
                    <EmptyState
                        icono={<Wrench className="size-7" />}
                        titulo={
                            filtro === null
                                ? 'Ninguna unidad con plan de mantenimiento'
                                : 'Ninguna unidad en este estado'
                        }
                        descripcion={
                            filtro === null
                                ? 'Las unidades aparecen acá cuando su marca tiene un plan cargado.'
                                : 'Quita el filtro para ver toda la flota.'
                        }
                    />
                }
                tarjeta={(unidad) => (
                    <Link
                        href={mantenimiento.show(unidad.id)}
                        className={cn(
                            'flex flex-col gap-2 rounded-xl border border-l-4 bg-card p-4 active:bg-accent/50',
                            bordeEstado[unidad.estado],
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-lg font-semibold tracking-wide">
                                {formatearPlaca(unidad.placa)}
                            </span>
                            <EstadoMantenimientoBadge
                                estado={unidad.estado}
                                label={unidad.estado_label}
                            />
                        </div>
                        <div className="flex items-center justify-between gap-2 text-sm">
                            <span>
                                {unidad.proximo ? (
                                    <>
                                        Próximo:{' '}
                                        <strong>{unidad.proximo.nombre}</strong>
                                    </>
                                ) : (
                                    <span className="text-muted-foreground">
                                        Sin servicios registrados
                                    </span>
                                )}
                            </span>
                            {unidad.proximo && (
                                <FaltanKm
                                    faltan={unidad.proximo.faltan_km}
                                    estado={unidad.proximo.estado}
                                />
                            )}
                        </div>
                        <KmActual unidad={unidad} />
                    </Link>
                )}
                encabezado={
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Placa</TableHead>
                        <TableHead>Marca</TableHead>
                        <TableHead className="text-right">Km actual</TableHead>
                        <TableHead>Próximo servicio</TableHead>
                        <TableHead className="text-right">A los</TableHead>
                        <TableHead className="text-right">Faltan</TableHead>
                        <TableHead>Estado</TableHead>
                    </TableRow>
                }
                fila={(unidad) => (
                    <TableRow
                        onClick={() => irA(unidad)}
                        className="cursor-pointer"
                    >
                        <TableCell className="font-medium">
                            <Link
                                href={mantenimiento.show(unidad.id)}
                                className="hover:underline"
                            >
                                {formatearPlaca(unidad.placa)}
                            </Link>
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {unidad.marca}
                        </TableCell>
                        <TableCell className="text-right">
                            <KmActual unidad={unidad} alineado />
                        </TableCell>
                        <TableCell>{unidad.proximo?.nombre ?? '—'}</TableCell>
                        <TableCell className="text-right text-muted-foreground tabular-nums">
                            {formatearKm(unidad.proximo?.proximo_km)}
                        </TableCell>
                        <TableCell className="text-right">
                            {unidad.proximo ? (
                                <FaltanKm
                                    faltan={unidad.proximo.faltan_km}
                                    estado={unidad.proximo.estado}
                                />
                            ) : (
                                '—'
                            )}
                        </TableCell>
                        <TableCell>
                            <EstadoMantenimientoBadge
                                estado={unidad.estado}
                                label={unidad.estado_label}
                            />
                        </TableCell>
                    </TableRow>
                )}
            />

            {puedeKm && (
                <>
                    <RegistrarKmDialog
                        tractos={tractos}
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
        </div>
    );
}

/** El último km con su fecha, avisando si ya es viejo. */
function KmActual({
    unidad,
    alineado = false,
}: {
    unidad: UnidadMantenimiento;
    alineado?: boolean;
}) {
    if (unidad.km_actual === null) {
        return (
            <span className="text-xs text-muted-foreground">
                Sin km registrado
            </span>
        );
    }

    return (
        <span
            className={cn(
                'flex flex-col text-xs text-muted-foreground',
                alineado && 'items-end',
            )}
        >
            <span className="text-sm text-foreground tabular-nums">
                {formatearKm(unidad.km_actual)}
            </span>
            <span
                className={cn(
                    unidad.km_desactualizado &&
                        'font-medium text-amber-700 dark:text-amber-400',
                )}
            >
                {unidad.km_desactualizado ? 'Desactualizado · ' : ''}
                {formatearFecha(unidad.km_fecha)}
            </span>
        </span>
    );
}
