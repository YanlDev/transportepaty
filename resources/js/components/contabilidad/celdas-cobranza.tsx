import { update } from '@/actions/App/Http/Controllers/FacturaController';
import { CeldaEntidad } from '@/components/contabilidad/celda-entidad';
import { CeldaMoneda } from '@/components/contabilidad/celda-moneda';
import { CeldaNuevaFactura } from '@/components/contabilidad/celda-nueva-factura';
import { TableCell } from '@/components/ui/table';
import { ValorEditable } from '@/components/valor-editable';
import { formatearFecha } from '@/lib/format';
import type { CuentaOpcion, FacturaResumen } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';

/**
 * Las seis columnas de la hoja del contador para una factura del viaje,
 * editables en el sitio. Cada celda guarda su propio campo por separado, así
 * que se llenan en el orden en que se van conociendo: primero el número de
 * factura, el monto cuando llega, y la fecha de pago recién cuando entra la
 * plata.
 *
 * Un viaje con varias facturas (flete y estadía) dibuja estas celdas una vez
 * por factura; `agregarOtra` va en la última y deja sumarle una más.
 */
export function CeldasCobranza({
    viajeId,
    noFacturable,
    motivoNoFacturable,
    factura,
    agregarOtra,
    cuentas,
    monedas,
    editable,
}: {
    viajeId: number;
    /** La GR se decidió no cobrar: en vez de «+ factura» se dice por qué. */
    noFacturable: boolean;
    motivoNoFacturable: string | null;
    /** Null cuando el viaje todavía no se facturó. */
    factura: FacturaResumen | null;
    agregarOtra: boolean;
    cuentas: CuentaOpcion[];
    monedas: EnumOption[];
    editable: boolean;
}) {
    if (factura === null) {
        return (
            <>
                <TableCell className="text-right text-muted-foreground/40">
                    —
                </TableCell>
                <TableCell className="font-mono text-xs whitespace-nowrap">
                    {noFacturable ? (
                        <span
                            className="font-sans text-muted-foreground"
                            title={motivoNoFacturable ?? undefined}
                        >
                            {motivoNoFacturable ?? 'No se cobra'}
                        </span>
                    ) : editable ? (
                        <CeldaNuevaFactura viajeId={viajeId} />
                    ) : (
                        <span className="text-muted-foreground/40">—</span>
                    )}
                </TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
            </>
        );
    }

    return (
        <>
            <TableCell className="whitespace-nowrap tabular-nums">
                <div className="flex items-center justify-end gap-0.5">
                    <CeldaMoneda
                        facturaId={factura.id}
                        moneda={factura.moneda}
                        simbolo={factura.simbolo}
                        monedas={monedas}
                        editable={editable}
                    />
                    <ValorEditable
                        url={update(factura.id).url}
                        campo="monto"
                        valor={factura.monto}
                        tipo="numero"
                        editable={editable}
                        className="text-right"
                        ancho="min-w-24"
                    >
                        {factura.monto === null ? null : (
                            <span className="font-medium">
                                {formatearMonto(factura.monto)}
                            </span>
                        )}
                    </ValorEditable>
                </div>
                {/* El monto es el total de la factura, no de la fila: sin
                    esto, doce filas de una factura se leen como doce cobros.
                    Debajo, lo que toca a cada GR, editable para las facturas
                    que se pactan por viaje (Minsur): se escribe el precio de
                    una y el total se calcula solo. */}
                {factura.viajes_count > 1 && (
                    <div className="flex items-center justify-end gap-1 text-xs text-muted-foreground">
                        <ValorEditable
                            url={update(factura.id).url}
                            campo="monto_por_viaje"
                            valor={
                                factura.monto === null
                                    ? null
                                    : redondear(
                                          factura.monto / factura.viajes_count,
                                      )
                            }
                            tipo="numero"
                            editable={editable}
                            className="text-right"
                            ancho="min-w-24"
                            etiqueta="Precio por GR"
                            placeholder="c/u"
                        >
                            {factura.monto === null
                                ? null
                                : `${formatearMonto(factura.monto / factura.viajes_count)} c/u`}
                        </ValorEditable>
                        <span className="whitespace-nowrap">
                            · {factura.viajes_count} GR
                        </span>
                    </div>
                )}
            </TableCell>

            <TableCell className="font-mono text-xs whitespace-nowrap tabular-nums">
                <ValorEditable
                    url={update(factura.id).url}
                    campo="numero"
                    valor={factura.numero}
                    editable={editable}
                    ancho="min-w-28"
                />
                {editable && agregarOtra && (
                    <CeldaNuevaFactura viajeId={viajeId} adicional />
                )}
            </TableCell>

            <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                <ValorEditable
                    url={update(factura.id).url}
                    campo="fecha_emision"
                    valor={factura.fecha_emision}
                    tipo="fecha"
                    editable={editable}
                    ancho="min-w-32"
                >
                    {factura.fecha_emision
                        ? formatearFecha(factura.fecha_emision)
                        : null}
                </ValorEditable>
            </TableCell>

            <TableCell className="whitespace-nowrap tabular-nums">
                <ValorEditable
                    url={update(factura.id).url}
                    campo="fecha_pago"
                    valor={factura.fecha_pago}
                    tipo="fecha"
                    editable={editable}
                    ancho="min-w-32"
                >
                    {factura.fecha_pago
                        ? formatearFecha(factura.fecha_pago)
                        : null}
                </ValorEditable>
            </TableCell>

            <TableCell className="whitespace-nowrap text-muted-foreground">
                <CeldaEntidad
                    facturaId={factura.id}
                    moneda={factura.moneda}
                    cuentaId={factura.cuenta_bancaria_id}
                    cuenta={factura.cuenta}
                    cuentas={cuentas}
                    editable={editable}
                />
                {/* Cobrada pero sin decir por dónde entró la plata: es
                    justamente el dato que este módulo existe para no perder. */}
                {factura.fecha_pago !== null &&
                    factura.cuenta_bancaria_id === null && (
                        <span className="block text-xs text-amber-700 dark:text-amber-500">
                            falta la entidad
                        </span>
                    )}
            </TableCell>

            <TableCell className="max-w-56 text-muted-foreground">
                <ValorEditable
                    url={update(factura.id).url}
                    campo="observacion"
                    valor={factura.observacion}
                    editable={editable}
                    className="truncate"
                    ancho="min-w-40"
                />
            </TableCell>
        </>
    );
}

function formatearMonto(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function redondear(monto: number): number {
    return Math.round(monto * 100) / 100;
}
