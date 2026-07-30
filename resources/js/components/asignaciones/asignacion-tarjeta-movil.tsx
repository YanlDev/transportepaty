import { Link } from '@inertiajs/react';
import { ArrowLeftRight, Link2Off, Pencil, Trash2 } from 'lucide-react';
import {
    edit,
    formularioReasignar,
} from '@/actions/App/Http/Controllers/AsignacionController';
import { show as showConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { DeleteAsignacionDialog } from '@/components/asignaciones/delete-asignacion-dialog';
import { LiberarAsignacionDialog } from '@/components/asignaciones/liberar-asignacion-dialog';
import { Copiable } from '@/components/copiable';
import { ResumenProblemas } from '@/components/semaforo-documental';
import { Button } from '@/components/ui/button';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { AsignacionListItem } from '@/types/fleet';

type Props = {
    asignacion: AsignacionListItem;
    puedeGestionar: boolean;
    mostrarHasta: boolean;
};

/**
 * La unidad como ficha: conductor arriba, los dos fierros con su TUC debajo.
 *
 * En móvil la tabla de asignaciones era la peor de todas —ocho columnas, dos de
 * ellas números largos de TUC— así que aquí se apila en dos bloques. El celular
 * es un enlace `tel:` porque en el teléfono lo que se quiere es llamar, no leer.
 */
export function AsignacionTarjetaMovil({
    asignacion,
    puedeGestionar,
    mostrarHasta,
}: Props) {
    return (
        <div className="group/fila flex flex-col gap-3 border bg-card p-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <Link
                        href={showConductor(asignacion.conductor.id)}
                        className="text-sm font-semibold underline-offset-2 hover:underline"
                    >
                        {asignacion.conductor.nombre_completo}
                    </Link>
                    {asignacion.conductor.telefono && (
                        <div className="mt-0.5 flex items-center gap-1">
                            <a
                                href={`tel:${asignacion.conductor.telefono}`}
                                className="text-xs text-muted-foreground tabular-nums underline-offset-2 hover:underline"
                            >
                                {asignacion.conductor.telefono}
                            </a>
                            <Copiable
                                valor={asignacion.conductor.telefono}
                                etiqueta="celular"
                            >
                                <span className="sr-only">
                                    {asignacion.conductor.telefono}
                                </span>
                            </Copiable>
                        </div>
                    )}
                </div>

                <ResumenProblemas estado={asignacion.documentacion} />
            </div>

            <dl className="grid grid-cols-2 gap-x-3 gap-y-2 border-t pt-2.5 text-xs">
                <Fierro
                    rotulo="Tracto"
                    placa={asignacion.tracto.placa}
                    tuc={asignacion.tracto.tuc_numero}
                />
                <Fierro
                    rotulo="Carreta"
                    placa={asignacion.carreta?.placa ?? null}
                    tuc={asignacion.carreta?.tuc_numero ?? null}
                />
            </dl>

            {(mostrarHasta || puedeGestionar) && (
                <div className="flex items-center justify-between gap-2 border-t pt-2">
                    <span className="text-xs text-muted-foreground">
                        {asignacion.hasta
                            ? `Hasta ${formatearFecha(asignacion.hasta)}`
                            : `Desde ${formatearFecha(asignacion.desde)}`}
                    </span>

                    {puedeGestionar && (
                        <div className="flex items-center gap-1">
                            {asignacion.vigente && (
                                <>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        size="sm"
                                        className="text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950"
                                    >
                                        <Link
                                            href={edit(asignacion.id)}
                                            aria-label={`Editar la asignación de ${asignacion.tracto.placa}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Link>
                                    </Button>
                                    <Button asChild variant="ghost" size="sm">
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
                                        asignacion={asignacion}
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
                    )}
                </div>
            )}
        </div>
    );
}

function Fierro({
    rotulo,
    placa,
    tuc,
}: {
    rotulo: string;
    placa: string | null;
    tuc: string | null;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                {rotulo}
            </dt>
            <dd className="mt-0.5 font-mono font-medium">
                <Copiable
                    valor={placa === null ? null : formatearPlaca(placa)}
                    etiqueta={`placa de ${rotulo.toLowerCase()}`}
                />
            </dd>
            <dd className="mt-0.5 text-[11px] text-muted-foreground tabular-nums">
                <Copiable
                    valor={tuc}
                    etiqueta={`TUC de ${rotulo.toLowerCase()}`}
                />
            </dd>
        </div>
    );
}
