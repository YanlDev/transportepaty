import { ArrowRight, Eye, Trash2 } from 'lucide-react';
import { Copiable } from '@/components/copiable';
import { Button } from '@/components/ui/button';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { AccionAnulacion } from '@/components/viajes/anulacion-viaje';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { DeleteViajeDialog } from '@/components/viajes/delete-viaje-dialog';
import { TipoCargaCelda } from '@/components/viajes/tipo-carga-celda';
import { formatearFecha, formatearPeso, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, ViajeListItem } from '@/types/fleet';

/**
 * El viaje como ficha, para el celular: la tabla tiene doce columnas y ahí no
 * entra ninguna. Arriba queda lo que sirve para ubicar el viaje de un vistazo
 * —fecha, cliente y ruta—, y debajo la unidad que lo hizo. El resto (GR del
 * remitente, direcciones completas, destinatario) vive en el detalle, que se
 * abre tocando la tarjeta.
 *
 * El área de tocar es la tarjeta entera menos los controles de abajo: los
 * botones y el selector de carga van fuera de ese área para que no se
 * dispare el detalle al usarlos.
 */
export function ViajeTarjetaMovil({
    viaje,
    tiposCarga,
    puedeEditar,
    colorGrupo,
    onVerDetalle,
}: {
    viaje: ViajeListItem;
    tiposCarga: EnumOption[];
    puedeEditar: boolean;
    colorGrupo: string | null;
    onVerDetalle: () => void;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-2 border bg-card p-3',
                colorGrupo && cn('border-l-2', colorGrupo),
                viaje.anulacion && 'bg-muted/40 opacity-60',
            )}
        >
            <button
                type="button"
                onClick={onVerDetalle}
                className="flex flex-col gap-2 text-left"
            >
                <div className="flex items-start justify-between gap-2">
                    <span className="text-sm font-semibold tabular-nums">
                        {formatearFecha(viaje.fecha_traslado)}
                    </span>
                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                        {formatearPeso(viaje.peso, viaje.unidad_peso)}
                    </span>
                </div>

                <ClienteChip cliente={viaje.cliente} />

                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span className="truncate">{viaje.origen_ciudad}</span>
                    <ArrowRight className="size-3 shrink-0" />
                    <span className="truncate">{viaje.destino_ciudad}</span>
                </p>

                <p className="text-xs text-muted-foreground">
                    <span className="font-mono">
                        {formatearPlaca(viaje.placa_tracto)}
                    </span>
                    {viaje.placa_carreta && (
                        <span className="font-mono">
                            {' / '}
                            {formatearPlaca(viaje.placa_carreta)}
                        </span>
                    )}
                    <span className="mx-1.5">·</span>
                    <span
                        className={cn(
                            'truncate',
                            !viaje.conductor_id &&
                                'text-amber-700 dark:text-amber-500',
                        )}
                    >
                        {viaje.conductor_nombre}
                    </span>
                </p>
            </button>

            <div className="flex items-center justify-between gap-2 border-t pt-2">
                <div className="flex items-center gap-2">
                    <span
                        className={cn(
                            'font-mono text-[11px] tabular-nums',
                            !viaje.anulacion &&
                                'text-blue-950 dark:text-blue-300',
                        )}
                    >
                        <Copiable valor={viaje.numero_gr} etiqueta="N° GR" />
                    </span>
                </div>

                <div className="flex items-center gap-1">
                    <TipoCargaCelda
                        viajeId={viaje.id}
                        valor={viaje.tipo_carga}
                        label={viaje.tipo_carga_label}
                        opciones={tiposCarga}
                        editable={puedeEditar}
                    />

                    <DocumentoVisorDialog
                        url={viaje.archivo_url ?? ''}
                        esPdf
                        titulo={`GR ${viaje.numero_gr}`}
                        detalle={`${viaje.cliente} · ${formatearFecha(viaje.fecha_traslado)}`}
                        trigger={
                            <Button
                                variant="ghost"
                                size="icon"
                                disabled={!viaje.archivo_url}
                                className="size-11 text-muted-foreground"
                                aria-label="Vista rápida de la GR"
                            >
                                <Eye className="size-5" />
                            </Button>
                        }
                    />

                    {puedeEditar && <AccionAnulacion viaje={viaje} grande />}

                    {puedeEditar && (
                        <DeleteViajeDialog
                            viaje={viaje}
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 text-muted-foreground hover:text-destructive"
                                    aria-label="Eliminar viaje"
                                >
                                    <Trash2 className="size-5" />
                                </Button>
                            }
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
