import { ArrowRight, Trash2 } from 'lucide-react';
import { Copiable } from '@/components/copiable';
import { Button } from '@/components/ui/button';
import { AccionAnulacion } from '@/components/viajes/anulacion-viaje';
import { ClienteConRemitente } from '@/components/viajes/cliente-con-remitente';
import { DeleteViajeDialog } from '@/components/viajes/delete-viaje-dialog';
import { TipoCargaCelda } from '@/components/viajes/tipo-carga-celda';
import { usePermisos } from '@/hooks/use-permisos';
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
    const { puede } = usePermisos();

    return (
        <div
            className={cn(
                'flex flex-col gap-2 rounded-xl border bg-card p-3',
                colorGrupo && cn('border-l-2', colorGrupo),
                viaje.anulacion && 'bg-muted/40 opacity-60',
            )}
        >
            <button
                type="button"
                onClick={onVerDetalle}
                className="flex flex-col gap-2 text-left"
            >
                {/* Arriba lo que se busca con la vista: cuándo y para quién. */}
                <div className="flex min-w-0 items-center justify-between gap-2">
                    <span className="shrink-0 text-sm font-semibold tabular-nums">
                        {formatearFecha(viaje.fecha_traslado)}
                    </span>
                    <span className="min-w-0 truncate">
                        <ClienteConRemitente
                            cliente={viaje.cliente}
                            remitente={viaje.remitente}
                        />
                    </span>
                </div>

                <p className="flex min-w-0 items-center gap-1.5 text-sm">
                    <span className="truncate">{viaje.origen_ciudad}</span>
                    <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                    <span className="truncate">{viaje.destino_ciudad}</span>
                    <span className="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums">
                        {formatearPeso(viaje.peso, viaje.unidad_peso)}
                    </span>
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
                            'font-mono text-xs whitespace-nowrap tabular-nums',
                            !viaje.anulacion && 'text-foreground',
                        )}
                    >
                        <Copiable valor={viaje.numero_gr} etiqueta="N° GR" />
                    </span>
                </div>

                {/* Sin «vista rápida» del PDF: tocar la tarjeta abre el
                    detalle, que ya tiene «Ver PDF», y a 360px no entraban
                    las cuatro acciones. */}
                <div className="flex shrink-0 items-center gap-1">
                    <TipoCargaCelda
                        viajeId={viaje.id}
                        valor={viaje.tipo_carga}
                        label={viaje.tipo_carga_label}
                        opciones={tiposCarga}
                        editable={puedeEditar}
                    />

                    {puede('viajes.anular') && (
                        <AccionAnulacion viaje={viaje} grande />
                    )}

                    {puede('viajes.eliminar') && (
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
