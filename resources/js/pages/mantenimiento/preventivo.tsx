import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ClipboardPlus,
    Clock,
    Gauge,
    Settings2,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { create as crearOrden } from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { edit as editarPlan } from '@/actions/App/Http/Controllers/PlanMantenimientoController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { EmptyState } from '@/components/empty-state';
import { ListadoResponsivo } from '@/components/listado-responsivo';
import {
    bordeEstado,
    EstadoMantenimientoBadge,
    FaltanKm,
    formatearKm,
} from '@/components/mantenimiento/estado-mantenimiento';
import {
    MarcaLogo,
    resumenNiveles,
} from '@/components/mantenimiento/marca-logo';
import {
    colorEstado,
    contarPorEstado,
    TarjetaMarca,
} from '@/components/mantenimiento/marca-tarjeta';
import { RegistrarKmDialog } from '@/components/mantenimiento/registrar-km-dialog';
import { Button } from '@/components/ui/button';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    EstadoMantenimiento,
    MarcaMantenimiento,
    ResumenMantenimiento,
    TractoKm,
    UnidadMantenimiento,
} from '@/types/mantenimiento';

type Props = {
    unidades: UnidadMantenimiento[];
    resumen: ResumenMantenimiento;
    /** Las marcas con plan, en orden alfabético. */
    marcas: MarcaMantenimiento[];
    /** Con lo que se abre: desde el tablero o al volver de un plan. */
    filtros: {
        marca: string | null;
        estado: EstadoMantenimiento | null;
        desactualizados: boolean;
    };
    tractos: TractoKm[];
};

/**
 * El preventivo de la flota: las unidades ordenadas desde el servidor —lo
 * vencido primero, luego lo que está por tocar— para que lo urgente quede
 * arriba sin filtrar nada. Las tarjetas de marca filtran y muestran su plan.
 */
export default function MantenimientoPreventivo({
    unidades,
    resumen,
    marcas,
    filtros,
    tractos,
}: Props) {
    const { puede } = usePermisos();
    const puedeKm = puede('mantenimiento.registrar_km');
    const puedeOrdenes = puede('mantenimiento.gestionar');
    const puedeConfigurar = puede('mantenimiento.configurar');
    const [kmAbierto, setKmAbierto] = useState(false);
    const [filtro, setFiltro] = useState<EstadoMantenimiento | null>(
        filtros.estado,
    );
    const [planId, setPlanId] = useState<number | null>(
        () => marcas.find((item) => item.marca === filtros.marca)?.id ?? null,
    );
    const [soloDesactualizados, setSoloDesactualizados] = useState(
        filtros.desactualizados,
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Preventivo', href: mantenimiento.preventivo().url },
        ],
    });

    const seleccionada = marcas.find((item) => item.id === planId) ?? null;

    const deLaMarca =
        planId === null
            ? unidades
            : unidades.filter((unidad) => unidad.plan_id === planId);

    const conteo = contarPorEstado(deLaMarca);
    const desactualizados = deLaMarca.filter(
        (unidad) => unidad.km_desactualizado,
    ).length;

    const visibles = deLaMarca.filter(
        (unidad) =>
            (filtro === null || unidad.estado === filtro) &&
            (!soloDesactualizados || unidad.km_desactualizado),
    );

    const irA = (unidad: UnidadMantenimiento) =>
        router.visit(mantenimiento.show(unidad.id));

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Preventivo" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    Preventivo X · S · M · L · {unidades.length}{' '}
                    {unidades.length === 1 ? 'unidad' : 'unidades'} de{' '}
                    {marcas.length} {marcas.length === 1 ? 'marca' : 'marcas'}
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

            {/* Una tarjeta por marca: tocarla deja solo sus unidades. */}
            <div className="grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-4">
                {marcas.map((item) => (
                    <TarjetaMarca
                        key={item.id}
                        marca={item}
                        conteo={contarPorEstado(
                            unidades.filter(
                                (unidad) => unidad.plan_id === item.id,
                            ),
                        )}
                        activa={item.id === planId}
                        onClick={() =>
                            setPlanId(item.id === planId ? null : item.id)
                        }
                    />
                ))}
            </div>

            {seleccionada && (
                <div className="flex flex-col gap-3 rounded-xl border bg-muted/40 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <MarcaLogo
                            marca={seleccionada.marca}
                            logoUrl={seleccionada.logo_url}
                            className="h-10 w-14"
                        />
                        <div className="flex flex-col">
                            <span className="font-medium">
                                {seleccionada.nombre}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {resumenNiveles(seleccionada.niveles)}
                            </span>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setPlanId(null)}
                        >
                            <X className="size-4" />
                            Todas las marcas
                        </Button>
                        {puedeConfigurar && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={editarPlan(seleccionada.id)}>
                                    <Settings2 className="size-4" />
                                    Configurar plan
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}

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
                                colorEstado[item.estado],
                            )}
                        >
                            {conteo[item.estado]}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {item.label}
                        </span>
                    </button>
                ))}
            </div>

            {desactualizados > 0 && (
                <button
                    type="button"
                    onClick={() => setSoloDesactualizados(!soloDesactualizados)}
                    aria-pressed={soloDesactualizados}
                    className={cn(
                        'flex items-center gap-2 self-start rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950/70',
                        soloDesactualizados &&
                            'ring-2 ring-amber-500 ring-offset-1 ring-offset-background',
                    )}
                >
                    <Clock className="size-4" />
                    {desactualizados}{' '}
                    {desactualizados === 1
                        ? 'unidad con el km desactualizado'
                        : 'unidades con el km desactualizado'}
                    {soloDesactualizados && <X className="size-3.5" />}
                </button>
            )}

            <ListadoResponsivo
                items={visibles}
                clave={(unidad) => unidad.id}
                vacio={
                    <EmptyState
                        icono={<Wrench className="size-7" />}
                        titulo={
                            deLaMarca.length === 0
                                ? 'Ninguna unidad con plan de mantenimiento'
                                : 'Ninguna unidad con este filtro'
                        }
                        descripcion={
                            deLaMarca.length === 0
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
