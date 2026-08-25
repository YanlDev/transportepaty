import { FileText, MapPin } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { ViajeListItem } from '@/types/fleet';

type Props = {
    /** `null` cierra el diálogo — controlado por quién lo usa, no hay estado propio de "cuál viaje". */
    viaje: ViajeListItem | null;
    onOpenChange: (abierto: boolean) => void;
};

/**
 * El detalle completo de un viaje, para cuando la fila de la tabla no
 * alcanza (destino completo, origen, documento). Solo trae la GR
 * transportista —la que emite Paty—: la guía remitente del cliente se
 * maneja aparte (bot de descarga desde SUNAT), no vive en este diálogo.
 */
export function ViajeDetalleDialog({ viaje, onOpenChange }: Props) {
    return (
        <Dialog open={viaje !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {viaje && (
                    <>
                        <DialogHeader>
                            <ClienteChip
                                cliente={viaje.cliente}
                                className="self-start"
                            />
                            <DialogTitle className="font-mono text-lg tabular-nums">
                                {viaje.numero_gr}
                            </DialogTitle>
                            <p className="text-sm text-muted-foreground">
                                Viaje del {formatearFecha(viaje.fecha_traslado)}
                            </p>
                        </DialogHeader>

                        <div className="grid grid-cols-2 gap-x-5 gap-y-4 text-sm">
                            <Campo etiqueta="Tracto / Carreta">
                                <span className="font-medium tabular-nums">
                                    {formatearPlaca(viaje.placa_tracto)}
                                    {viaje.placa_carreta &&
                                        ` · ${formatearPlaca(viaje.placa_carreta)}`}
                                </span>
                            </Campo>
                            <Campo etiqueta="Conductor">
                                <span className="font-medium">
                                    {viaje.conductor_nombre}
                                </span>
                            </Campo>
                            <Campo etiqueta="Tipo de carga">
                                <TipoCargaBadge
                                    valor={viaje.tipo_carga}
                                    label={viaje.tipo_carga_label}
                                />
                            </Campo>
                            <Campo etiqueta="Peso bruto">
                                <span className="font-medium tabular-nums">
                                    {viaje.peso.toLocaleString('es-PE')}{' '}
                                    {viaje.unidad_peso}
                                </span>
                            </Campo>

                            <div className="col-span-2 grid grid-cols-2 gap-x-5 gap-y-4 border-t pt-4">
                                <Campo etiqueta="Origen" icono>
                                    {viaje.origen}
                                </Campo>
                                <Campo etiqueta="Destino" icono>
                                    {viaje.destino}
                                </Campo>
                            </div>
                        </div>

                        <div>
                            <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Documento
                            </p>
                            <div className="flex items-center gap-3 rounded-lg border p-3">
                                <div className="grid size-9 shrink-0 place-items-center rounded-md bg-accent text-primary">
                                    <FileText className="size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold">
                                        GR Transportista
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        Emitida por Paty · {viaje.numero_gr}
                                    </p>
                                </div>
                                <DocumentoVisorDialog
                                    url={viaje.archivo_url ?? ''}
                                    esPdf
                                    titulo={`GR ${viaje.numero_gr}`}
                                    detalle={`${viaje.cliente} · ${formatearFecha(viaje.fecha_traslado)}`}
                                    trigger={
                                        <button
                                            type="button"
                                            disabled={!viaje.archivo_url}
                                            className="h-8 shrink-0 rounded-md border px-3 text-xs font-medium hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                                        >
                                            Ver PDF
                                        </button>
                                    }
                                />
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function Campo({
    etiqueta,
    icono,
    children,
}: {
    etiqueta: string;
    icono?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <p className="mb-1 flex items-center gap-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                {icono && <MapPin className="size-3" />}
                {etiqueta}
            </p>
            <div className="leading-snug break-words">{children}</div>
        </div>
    );
}
