import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Link2Off,
    Pencil,
    Plus,
    Trash2,
    Truck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import asignaciones, {
    create,
    edit,
    formularioReasignar,
} from '@/actions/App/Http/Controllers/AsignacionController';
import { show as showConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { AsignacionTarjetaMovil } from '@/components/asignaciones/asignacion-tarjeta-movil';
import { DeleteAsignacionDialog } from '@/components/asignaciones/delete-asignacion-dialog';
import { LiberarAsignacionDialog } from '@/components/asignaciones/liberar-asignacion-dialog';
import { Copiable } from '@/components/copiable';
import { FiltrosBarra } from '@/components/filtros-barra';
import { ResumenProblemas } from '@/components/semaforo-documental';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { AsignacionListItem, EnumOption, Paginator } from '@/types/fleet';

type Filtros = {
    buscar: string;
    estado: string;
    caja: string;
};

type Props = {
    asignaciones: Paginator<AsignacionListItem>;
    filtros: Filtros;
    cajas: EnumOption[];
};

// Radix Select no admite items con value vacío.
const TODAS_LAS_CAJAS = 'todas';

/** Lo que se ve al entrar sin tocar nada; no cuenta como filtro aplicado. */
const ESTADO_POR_DEFECTO = 'vigentes';

const estadoOpciones = [
    { value: ESTADO_POR_DEFECTO, label: 'Unidades vigentes' },
    { value: 'historial', label: 'Historial' },
    { value: 'todas', label: 'Todas' },
];

export default function AsignacionesIndex({
    asignaciones: paginador,
    filtros,
    cajas,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    const mostrarHasta = filtros.estado !== 'vigentes';

    const aplicar = (cambios: Partial<Filtros>) => {
        router.get(
            asignaciones.index().url,
            { ...filtros, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (buscar === (filtros.buscar ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                asignaciones.index().url,
                { ...filtros, buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Asignaciones" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Asignaciones
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1 ? 'asignación' : 'asignaciones'}{' '}
                        {filtros.estado === 'vigentes'
                            ? 'vigentes'
                            : 'en el listado'}
                    </p>
                </div>

                {puedeGestionar && (
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nueva asignación
                        </Link>
                    </Button>
                )}
            </div>

            <FiltrosBarra
                buscar={buscar}
                onBuscar={setBuscar}
                placeholder="Buscar por conductor, tracto o carreta..."
                etiquetaBusqueda="Buscar asignaciones"
                activos={
                    (filtros.caja ? 1 : 0) +
                    (filtros.estado === ESTADO_POR_DEFECTO ? 0 : 1)
                }
                onLimpiar={() =>
                    aplicar({ caja: '', estado: ESTADO_POR_DEFECTO })
                }
            >
                <Select
                    value={filtros.caja || TODAS_LAS_CAJAS}
                    onValueChange={(value) =>
                        aplicar({
                            caja: value === TODAS_LAS_CAJAS ? '' : value,
                        })
                    }
                >
                    <SelectTrigger
                        aria-label="Caja"
                        className="h-9 w-full sm:w-auto sm:min-w-36"
                    >
                        <SelectValue placeholder="Caja" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={TODAS_LAS_CAJAS}>
                            Todas las cajas
                        </SelectItem>
                        {cajas.map((caja) => (
                            <SelectItem key={caja.value} value={caja.value}>
                                {caja.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select
                    value={filtros.estado}
                    onValueChange={(value) => aplicar({ estado: value })}
                >
                    <SelectTrigger
                        aria-label="Estado de la unidad"
                        className="h-9 w-full sm:w-auto sm:min-w-40"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {estadoOpciones.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                {opcion.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FiltrosBarra>

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-none bg-muted text-muted-foreground">
                        <Truck className="size-7" />
                    </div>
                    <p className="font-medium">
                        No se encontraron asignaciones
                    </p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda
                        {puedeGestionar && ' o arma tu primera unidad'}.
                    </p>
                    {puedeGestionar && (
                        <Button asChild variant="outline" className="mt-6">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nueva asignación
                            </Link>
                        </Button>
                    )}
                </div>
            ) : (
                <>
                    <div className="flex flex-col gap-2 sm:hidden">
                        {paginador.data.map((asignacion) => (
                            <AsignacionTarjetaMovil
                                key={asignacion.id}
                                asignacion={asignacion}
                                puedeGestionar={puedeGestionar}
                                mostrarHasta={mostrarHasta}
                            />
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto border sm:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Tracto</TableHead>
                                    <TableHead>Carreta</TableHead>
                                    <TableHead>TUC tracto</TableHead>
                                    <TableHead>TUC carreta</TableHead>
                                    <TableHead>Conductor</TableHead>
                                    <TableHead>Celular</TableHead>
                                    <TableHead>Documentación</TableHead>
                                    {mostrarHasta && (
                                        <TableHead>Hasta</TableHead>
                                    )}
                                    {puedeGestionar && (
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((asignacion) => (
                                    <TableRow
                                        key={asignacion.id}
                                        className="group/fila"
                                        // La fecha de asignación no ocupa
                                        // columna, pero queda a un hover.
                                        title={`Asignada desde ${formatearFecha(asignacion.desde)}`}
                                    >
                                        <TableCell className="font-mono font-medium">
                                            <Copiable
                                                valor={formatearPlaca(
                                                    asignacion.tracto.placa,
                                                )}
                                                etiqueta="placa"
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-muted-foreground">
                                            <Copiable
                                                valor={
                                                    asignacion.carreta
                                                        ? formatearPlaca(
                                                              asignacion.carreta
                                                                  .placa,
                                                          )
                                                        : null
                                                }
                                                etiqueta="placa de carreta"
                                            />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            <Copiable
                                                valor={
                                                    asignacion.tracto.tuc_numero
                                                }
                                                etiqueta="TUC del tracto"
                                            />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            <Copiable
                                                valor={
                                                    asignacion.carreta
                                                        ?.tuc_numero ?? null
                                                }
                                                etiqueta="TUC de la carreta"
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={showConductor(
                                                    asignacion.conductor.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {
                                                    asignacion.conductor
                                                        .nombre_completo
                                                }
                                            </Link>
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            <Copiable
                                                valor={
                                                    asignacion.conductor
                                                        .telefono
                                                }
                                                etiqueta="celular"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <ResumenProblemas
                                                estado={
                                                    asignacion.documentacion
                                                }
                                            />
                                        </TableCell>
                                        {mostrarHasta && (
                                            <TableCell className="tabular-nums">
                                                {asignacion.hasta ? (
                                                    formatearFecha(
                                                        asignacion.hasta,
                                                    )
                                                ) : (
                                                    <span className="inline-flex items-center rounded-none bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/20">
                                                        Vigente
                                                    </span>
                                                )}
                                            </TableCell>
                                        )}
                                        {puedeGestionar && (
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {asignacion.vigente && (
                                                        <>
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950"
                                                            >
                                                                <Link
                                                                    href={edit(
                                                                        asignacion.id,
                                                                    )}
                                                                    aria-label={`Editar la asignación de ${asignacion.tracto.placa}`}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link
                                                                    href={formularioReasignar(
                                                                        asignacion.id,
                                                                    )}
                                                                    aria-label={`Reasignar el conductor de ${asignacion.tracto.placa} a otra unidad`}
                                                                >
                                                                    <ArrowLeftRight className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <LiberarAsignacionDialog
                                                                asignacion={
                                                                    asignacion
                                                                }
                                                                trigger={
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        aria-label={`Liberar la unidad ${asignacion.tracto.placa}`}
                                                                    >
                                                                        <Link2Off className="size-4" />
                                                                    </Button>
                                                                }
                                                            />
                                                        </>
                                                    )}
                                                    <DeleteAsignacionDialog
                                                        asignacion={asignacion}
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                                aria-label={`Eliminar la asignación de ${asignacion.tracto.placa}`}
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {paginador.last_page > 1 && (
                        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
                            <p className="text-sm text-muted-foreground">
                                Mostrando {paginador.from}–{paginador.to} de{' '}
                                {paginador.total}
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {paginador.links.map((link, indice) => (
                                    <Button
                                        key={indice}
                                        asChild={!!link.url}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        disabled={!link.url}
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                preserveScroll
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

AsignacionesIndex.layout = {
    breadcrumbs: [{ title: 'Asignaciones', href: asignaciones.index().url }],
};
