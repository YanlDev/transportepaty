import { Eye, Trash2 } from 'lucide-react';
import { Copiable } from '@/components/copiable';
import { DireccionCelda } from '@/components/direccion-celda';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import {
    AccionAnulacion,
    EtiquetaAnulada,
} from '@/components/viajes/anulacion-viaje';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { ConductorCelda } from '@/components/viajes/conductor-celda';
import { DeleteViajeDialog } from '@/components/viajes/delete-viaje-dialog';
import { GuiasRemitenteCelda } from '@/components/viajes/guias-remitente-celda';
import { PlacaCelda } from '@/components/viajes/placa-celda';
import { TipoCargaCelda } from '@/components/viajes/tipo-carga-celda';
import type { FilaAgrupada } from '@/lib/agrupar-viajes';
import { formatearFecha, formatearPeso } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, ViajeListItem } from '@/types/fleet';

type Props = {
    filas: FilaAgrupada<ViajeListItem>[];
    tiposCarga: EnumOption[];
    puedeEditar: boolean;
    onVerDetalle: (viaje: ViajeListItem) => void;
};

/**
 * El historial de GR tal como se subieron. El borde de color a la izquierda
 * agrupa las guías que salieron en el mismo camión.
 *
 * La fila entera abre el detalle, menos donde hay un control propio: sin ese
 * chequeo, borrar un viaje abriría además el panel del viaje que se borró.
 *
 * Una GR anulada se ve apagada y con el número tachado: existió, pero no
 * cuenta como viaje.
 */
export function TablaViajes({
    filas,
    tiposCarga,
    puedeEditar,
    onVerDetalle,
}: Props) {
    return (
        <div className="hidden overflow-x-auto rounded-xl border shadow-sm sm:block">
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Fecha</TableHead>
                        <TableHead>N° GR</TableHead>
                        <TableHead>GR Remitente</TableHead>
                        <TableHead>Tracto</TableHead>
                        <TableHead>Carreta</TableHead>
                        <TableHead>Conductor</TableHead>
                        <TableHead>Cliente</TableHead>
                        <TableHead>Origen</TableHead>
                        <TableHead>Destino</TableHead>
                        <TableHead>Tipo de carga</TableHead>
                        <TableHead className="text-right">Peso</TableHead>
                        <TableHead className="w-0" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {filas.map(({ viaje, colorGrupo }) => (
                        <TableRow
                            key={viaje.id}
                            className={cn(
                                'group/fila cursor-pointer',
                                colorGrupo && cn('border-l-2', colorGrupo),
                                viaje.anulacion &&
                                    'bg-muted/40 text-muted-foreground opacity-60 hover:opacity-90',
                            )}
                            onClick={(evento) => {
                                const objetivo = evento.target as HTMLElement;

                                if (
                                    objetivo.closest(
                                        'a, button, [role="menuitem"]',
                                    )
                                ) {
                                    return;
                                }

                                onVerDetalle(viaje);
                            }}
                        >
                            <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                                {formatearFecha(viaje.fecha_traslado)}
                            </TableCell>
                            <TableCell
                                className={cn(
                                    'font-mono text-[11px] whitespace-nowrap tabular-nums',
                                    viaje.anulacion
                                        ? 'text-muted-foreground'
                                        : 'text-blue-950 dark:text-blue-300',
                                )}
                            >
                                <div className="flex items-center gap-1.5">
                                    <span
                                        className={cn(
                                            viaje.anulacion && 'line-through',
                                        )}
                                    >
                                        <Copiable
                                            valor={viaje.numero_gr}
                                            etiqueta="N° GR"
                                        />
                                    </span>
                                    {viaje.anulacion && (
                                        <EtiquetaAnulada
                                            anulacion={viaje.anulacion}
                                        />
                                    )}
                                </div>
                            </TableCell>
                            <TableCell className="font-mono text-[11px] whitespace-nowrap text-marca-600 tabular-nums dark:text-marca-400">
                                <GuiasRemitenteCelda
                                    guias={viaje.guias_remitente}
                                />
                            </TableCell>
                            <TableCell className="text-[11px] whitespace-nowrap">
                                <PlacaCelda
                                    placa={viaje.placa_tracto}
                                    vehiculoId={viaje.tracto_id}
                                />
                            </TableCell>
                            <TableCell className="text-[11px] whitespace-nowrap">
                                {viaje.placa_carreta ? (
                                    <PlacaCelda
                                        placa={viaje.placa_carreta}
                                        vehiculoId={viaje.carreta_id}
                                    />
                                ) : (
                                    '—'
                                )}
                            </TableCell>
                            <TableCell className="text-[11px] whitespace-nowrap">
                                <ConductorCelda
                                    nombre={viaje.conductor_nombre}
                                    conductorId={viaje.conductor_id}
                                />
                            </TableCell>
                            <TableCell className="max-w-[160px] overflow-hidden">
                                <ClienteChip cliente={viaje.cliente} />
                            </TableCell>
                            <TableCell className="max-w-[160px] overflow-hidden">
                                <DireccionCelda
                                    ciudad={viaje.origen_ciudad}
                                    direccion={viaje.origen}
                                />
                            </TableCell>
                            <TableCell className="max-w-[160px] overflow-hidden">
                                <DireccionCelda
                                    ciudad={viaje.destino_ciudad}
                                    direccion={viaje.destino}
                                />
                            </TableCell>
                            <TableCell>
                                <TipoCargaCelda
                                    viajeId={viaje.id}
                                    valor={viaje.tipo_carga}
                                    label={viaje.tipo_carga_label}
                                    opciones={tiposCarga}
                                    editable={puedeEditar}
                                />
                            </TableCell>
                            <TableCell className="text-right whitespace-nowrap tabular-nums">
                                {formatearPeso(viaje.peso, viaje.unidad_peso)}
                            </TableCell>
                            <TableCell>
                                <div className="flex items-center justify-end gap-1">
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
                                                className="size-8 text-muted-foreground"
                                                aria-label="Vista rápida de la GR"
                                            >
                                                <Eye className="size-4" />
                                            </Button>
                                        }
                                    />
                                    {puedeEditar && (
                                        <AccionAnulacion viaje={viaje} />
                                    )}
                                    {puedeEditar && (
                                        <DeleteViajeDialog
                                            viaje={viaje}
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-muted-foreground hover:text-destructive"
                                                    aria-label="Eliminar viaje"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            }
                                        />
                                    )}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
