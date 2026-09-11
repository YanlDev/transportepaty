import { AccionesFila } from '@/components/contabilidad/acciones-fila';
import { CeldasCobranza } from '@/components/contabilidad/celdas-cobranza';
import { EstadoCobranzaBadge } from '@/components/contabilidad/estado-cobranza-badge';
import { Copiable } from '@/components/copiable';
import { DireccionCelda } from '@/components/direccion-celda';
import { Checkbox } from '@/components/ui/checkbox';
import { TableCell, TableRow } from '@/components/ui/table';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { ConductorCelda } from '@/components/viajes/conductor-celda';
import { GuiasRemitenteCelda } from '@/components/viajes/guias-remitente-celda';
import { PlacaCelda } from '@/components/viajes/placa-celda';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { formatearFecha, formatearPeso } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CuentaOpcion, ViajeContable } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';
import { INICIO_COBRANZA } from './columnas-cobranza';

type Props = {
    viaje: ViajeContable;
    /** Color del grupo de GR que viajaron juntas, o null si va sola. */
    colorGrupo: string | null;
    cuentas: CuentaOpcion[];
    monedas: EnumOption[];
    puedeFacturar: boolean;
    seleccionado: boolean;
    onSeleccionar: (viajeId: number) => void;
};

/** Una GR en la hoja de cobranza: la operación a la izquierda, la plata a la derecha. */
export function FilaCobranza({
    viaje,
    colorGrupo,
    cuentas,
    monedas,
    puedeFacturar,
    seleccionado,
    onSeleccionar,
}: Props) {
    return (
        <TableRow
            className={cn(
                colorGrupo && cn('border-l-2', colorGrupo),
                seleccionado && 'bg-primary/5',
            )}
        >
            {puedeFacturar && (
                <TableCell>
                    {/* Un viaje ya facturado no se puede elegir: volver a
                        facturarlo es siempre un error, así que la casilla ni
                        siquiera aparece. */}
                    {viaje.factura === null && (
                        <Checkbox
                            aria-label={`Seleccionar la GR ${viaje.numero_gr}`}
                            checked={seleccionado}
                            onCheckedChange={() => onSeleccionar(viaje.id)}
                        />
                    )}
                </TableCell>
            )}

            <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                {formatearFecha(viaje.fecha_traslado)}
            </TableCell>
            <TableCell className="font-mono text-[11px] whitespace-nowrap text-blue-950 tabular-nums dark:text-blue-300">
                <Copiable valor={viaje.numero_gr} etiqueta="N° GR" />
            </TableCell>
            <TableCell className="font-mono text-[11px] whitespace-nowrap text-indigo-600 tabular-nums dark:text-indigo-400">
                <GuiasRemitenteCelda guias={viaje.guias_remitente} />
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
                {/* Sin editar: el tipo de carga lo corrige la operación en
                    `/viajes`, no la cobranza. */}
                <TipoCargaBadge
                    valor={viaje.tipo_carga}
                    label={viaje.tipo_carga_label}
                />
            </TableCell>
            <TableCell className="text-right whitespace-nowrap tabular-nums">
                {formatearPeso(viaje.peso, viaje.unidad_peso)}
            </TableCell>

            <TableCell className={cn(INICIO_COBRANZA, 'whitespace-nowrap')}>
                <EstadoCobranzaBadge
                    estado={viaje.estado}
                    label={viaje.estado_label}
                    diasVencida={viaje.factura?.dias_vencida}
                />
            </TableCell>

            <CeldasCobranza
                viaje={viaje}
                cuentas={cuentas}
                monedas={monedas}
                editable={puedeFacturar}
            />

            <TableCell>
                <AccionesFila viaje={viaje} puedeFacturar={puedeFacturar} />
            </TableCell>
        </TableRow>
    );
}
