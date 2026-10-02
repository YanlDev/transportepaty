import { AccionesFila } from '@/components/contabilidad/acciones-fila';
import { CeldaGrFisica } from '@/components/contabilidad/celda-gr-fisica';
import { CeldasCobranza } from '@/components/contabilidad/celdas-cobranza';
import { EstadoCobranzaBadge } from '@/components/contabilidad/estado-cobranza-badge';
import { VerGuia } from '@/components/contabilidad/ver-guia';
import { Copiable } from '@/components/copiable';
import { DireccionCelda } from '@/components/direccion-celda';
import { Checkbox } from '@/components/ui/checkbox';
import { TableCell, TableRow } from '@/components/ui/table';
import { ClienteConRemitente } from '@/components/viajes/cliente-con-remitente';
import { ConductorCelda } from '@/components/viajes/conductor-celda';
import { GuiasRemitenteCelda } from '@/components/viajes/guias-remitente-celda';
import { PlacaCelda } from '@/components/viajes/placa-celda';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { diasVencidaMayor, esFacturable } from '@/lib/cobranza';
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
    onSeleccionar: (viaje: ViajeContable) => void;
};

/**
 * Una GR en la hoja de cobranza: la operación a la izquierda, la plata a la
 * derecha. Con varias facturas (flete y estadía), la GR se abre en una línea
 * por factura: la operación y el estado ocupan todas con `rowSpan`, y cada
 * factura conserva sus propias celdas editables y sus acciones.
 */
export function FilaCobranza({
    viaje,
    colorGrupo,
    cuentas,
    monedas,
    puedeFacturar,
    seleccionado,
    onSeleccionar,
}: Props) {
    // Sin facturas igual hay una línea: la de «+ factura».
    const lineas = viaje.facturas.length === 0 ? [null] : viaje.facturas;
    const alto = lineas.length;
    const claseLinea = cn(seleccionado && 'bg-primary/5');

    return (
        <>
            {lineas.map((factura, indice) => {
                const esPrimera = indice === 0;
                const esUltima = indice === alto - 1;

                return (
                    <TableRow
                        key={factura?.id ?? 'sin-factura'}
                        className={cn(
                            claseLinea,
                            esPrimera &&
                                colorGrupo &&
                                cn('border-l-2', colorGrupo),
                            // Las líneas de una misma GR no se separan: el
                            // borde va solo debajo de la última.
                            !esUltima && 'border-b-0',
                        )}
                    >
                        {esPrimera && (
                            <ColumnasOperacion
                                viaje={viaje}
                                alto={alto}
                                puedeFacturar={puedeFacturar}
                                seleccionado={seleccionado}
                                onSeleccionar={onSeleccionar}
                            />
                        )}

                        <CeldasCobranza
                            viajeId={viaje.id}
                            noFacturable={!esFacturable(viaje)}
                            motivoNoFacturable={viaje.motivo_no_facturable}
                            factura={factura}
                            agregarOtra={esUltima}
                            cuentas={cuentas}
                            monedas={monedas}
                            editable={puedeFacturar}
                        />

                        <TableCell>
                            <AccionesFila
                                viaje={viaje}
                                factura={factura}
                                puedeFacturar={puedeFacturar}
                            />
                        </TableCell>
                    </TableRow>
                );
            })}
        </>
    );
}

/**
 * Lo que la GR es una sola vez aunque tenga varias facturas: la casilla, el
 * viaje tal como ocurrió y el estado del cobro, que resume todas.
 */
function ColumnasOperacion({
    viaje,
    alto,
    puedeFacturar,
    seleccionado,
    onSeleccionar,
}: {
    viaje: ViajeContable;
    alto: number;
    puedeFacturar: boolean;
    seleccionado: boolean;
    onSeleccionar: (viaje: ViajeContable) => void;
}) {
    return (
        <>
            {puedeFacturar && (
                <TableCell rowSpan={alto} className="align-top">
                    {/* También en un viaje ya facturado: puede llevar otra
                        factura aparte, como la estadía. No en uno que se
                        decidió no cobrar. */}
                    {esFacturable(viaje) && (
                        <Checkbox
                            aria-label={`Seleccionar la GR ${viaje.numero_gr}`}
                            checked={seleccionado}
                            onCheckedChange={() => onSeleccionar(viaje)}
                        />
                    )}
                </TableCell>
            )}

            <TableCell
                rowSpan={alto}
                className="whitespace-nowrap text-muted-foreground tabular-nums"
            >
                {formatearFecha(viaje.fecha_traslado)}
            </TableCell>
            <TableCell
                rowSpan={alto}
                className="font-mono text-xs whitespace-nowrap text-foreground tabular-nums"
            >
                <Copiable valor={viaje.numero_gr} etiqueta="N° GR" />
            </TableCell>
            <TableCell
                rowSpan={alto}
                className="font-mono text-xs whitespace-nowrap text-marca-600 tabular-nums dark:text-marca-400"
            >
                <GuiasRemitenteCelda guias={viaje.guias_remitente} />
            </TableCell>
            <TableCell rowSpan={alto} className="text-center">
                <CeldaGrFisica
                    viajeId={viaje.id}
                    numeroGr={viaje.numero_gr}
                    recibidaAt={viaje.gr_fisica_recibida_at}
                    editable={puedeFacturar}
                />
            </TableCell>
            <TableCell rowSpan={alto} className="text-xs whitespace-nowrap">
                <PlacaCelda
                    placa={viaje.placa_tracto}
                    vehiculoId={viaje.tracto_id}
                />
            </TableCell>
            <TableCell rowSpan={alto} className="text-xs whitespace-nowrap">
                {viaje.placa_carreta ? (
                    <PlacaCelda
                        placa={viaje.placa_carreta}
                        vehiculoId={viaje.carreta_id}
                    />
                ) : (
                    '—'
                )}
            </TableCell>
            <TableCell rowSpan={alto} className="text-xs whitespace-nowrap">
                <ConductorCelda
                    nombre={viaje.conductor_nombre}
                    conductorId={viaje.conductor_id}
                />
            </TableCell>
            <TableCell rowSpan={alto} className="max-w-40 overflow-hidden">
                <ClienteConRemitente
                    cliente={viaje.cliente}
                    remitente={viaje.remitente}
                />
            </TableCell>
            <TableCell rowSpan={alto} className="max-w-40 overflow-hidden">
                <DireccionCelda
                    ciudad={viaje.origen_ciudad}
                    direccion={viaje.origen}
                />
            </TableCell>
            <TableCell rowSpan={alto} className="max-w-40 overflow-hidden">
                <DireccionCelda
                    ciudad={viaje.destino_ciudad}
                    direccion={viaje.destino}
                />
            </TableCell>
            <TableCell rowSpan={alto}>
                {/* Sin editar: el tipo de carga lo corrige la operación en
                    `/viajes`, no la cobranza. */}
                <TipoCargaBadge
                    valor={viaje.tipo_carga}
                    label={viaje.tipo_carga_label}
                />
            </TableCell>
            <TableCell
                rowSpan={alto}
                className="text-right whitespace-nowrap tabular-nums"
            >
                {formatearPeso(viaje.peso, viaje.unidad_peso)}
            </TableCell>
            <TableCell rowSpan={alto} className="w-0">
                <VerGuia viaje={viaje} />
            </TableCell>

            <TableCell
                rowSpan={alto}
                className={cn(INICIO_COBRANZA, 'whitespace-nowrap')}
            >
                <EstadoCobranzaBadge
                    estado={viaje.estado}
                    label={viaje.estado_label}
                    diasVencida={diasVencidaMayor(viaje)}
                />
            </TableCell>
        </>
    );
}
