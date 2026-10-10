import { FileText } from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/FacturaController';
import { CeldaEntidad } from '@/components/contabilidad/celda-entidad';
import { CeldaMoneda } from '@/components/contabilidad/celda-moneda';
import { CeldaNuevaFactura } from '@/components/contabilidad/celda-nueva-factura';
import { TableCell } from '@/components/ui/table';
import { ValorEditable } from '@/components/valor-editable';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CuentaOpcion, FacturaResumen } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';

/** Cuántas columnas dibuja una factura: tiene que coincidir con el encabezado. */
const COLUMNAS_FACTURA = 12;

/**
 * Las columnas de la hoja del contador para una factura del viaje, editables
 * en el sitio. Cada celda guarda su propio campo por separado, así que se
 * llenan en el orden en que se van conociendo: el número de factura, el flete
 * cuando llega, y las fechas de cobro recién cuando entra la plata.
 *
 * El flete se ve descompuesto como en la factura impresa —valor, IGV, total,
 * detracción y neto—. Se escribe el valor o el total, según cómo se pactó, y
 * el resto lo calcula el servidor. El cobro va en dos partes: el neto, que
 * entra a una cuenta de la empresa, y la detracción, que el cliente deposita
 * en el Banco de la Nación.
 *
 * Un viaje con varias facturas (flete y estadía) dibuja estas celdas una vez
 * por factura. Sumarle otra factura al viaje va en las acciones de la fila.
 */
export function CeldasCobranza({
    viajeId,
    noFacturable,
    motivoNoFacturable,
    factura,
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
    cuentas: CuentaOpcion[];
    monedas: EnumOption[];
    editable: boolean;
}) {
    if (factura === null) {
        // Las cinco cifras del flete vacías, después el número de factura
        // (donde se emite) y el resto de las columnas vacías.
        return (
            <>
                {Array.from({ length: 5 }, (_, indice) => (
                    <CeldaVacia key={`cifra-${indice}`} />
                ))}
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
                {Array.from({ length: COLUMNAS_FACTURA - 6 }, (_, indice) => (
                    <CeldaVacia key={`resto-${indice}`} />
                ))}
            </>
        );
    }

    const url = update(factura.id).url;
    const vencida = factura.dias_vencida !== null && factura.dias_vencida > 0;
    const llevaDetraccion = (factura.detraccion ?? 0) > 0;

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
                        url={url}
                        campo="monto"
                        valor={factura.monto}
                        tipo="numero"
                        editable={editable}
                        className="text-right"
                        ancho="min-w-24"
                        etiqueta="Valor flete (sin IGV)"
                    >
                        {factura.monto === null ? null : (
                            <span className="font-medium">
                                {formatearMonto(factura.monto)}
                            </span>
                        )}
                    </ValorEditable>
                </div>
                {/* El valor es el de la factura, no de la fila: sin esto,
                    doce filas de una factura se leen como doce cobros.
                    Debajo, lo que toca a cada GR, editable para las facturas
                    que se pactan por viaje (Minsur): se escribe el precio de
                    una y el valor se calcula solo. */}
                {factura.viajes_count > 1 && (
                    <div className="flex items-center justify-end gap-1 text-xs text-muted-foreground">
                        <ValorEditable
                            url={url}
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

            <CeldaCifra valor={factura.igv} />

            {/* Editable para el flete pactado con IGV adentro: se escribe el
                total y el valor sale dividiendo. */}
            <TableCell className="whitespace-nowrap tabular-nums">
                <ValorEditable
                    url={url}
                    campo="total"
                    valor={factura.total}
                    tipo="numero"
                    editable={editable}
                    className="text-right"
                    ancho="min-w-24"
                    etiqueta="Total flete (con IGV)"
                >
                    {factura.total === null
                        ? null
                        : formatearMonto(factura.total)}
                </ValorEditable>
            </TableCell>

            <CeldaCifra valor={factura.detraccion} />

            <CeldaCifra valor={factura.neto} destacada />

            <TableCell className="font-mono text-xs whitespace-nowrap tabular-nums">
                <div className="flex items-center gap-1">
                    <ValorEditable
                        url={url}
                        campo="numero"
                        valor={factura.numero}
                        editable={editable}
                        ancho="min-w-28"
                    />
                    {/* El PDF de la factura, si se subió: se abre aparte
                        para no perder la fila que se está llenando. */}
                    {factura.archivo_url && (
                        <a
                            href={factura.archivo_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-muted-foreground hover:text-foreground"
                            aria-label={`Ver el PDF de la factura ${factura.numero}`}
                            title="Ver el PDF de la factura"
                        >
                            <FileText className="size-3.5" />
                        </a>
                    )}
                </div>
            </TableCell>

            <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                <ValorEditable
                    url={url}
                    campo="fecha_emision"
                    valor={factura.fecha_emision}
                    tipo="fecha"
                    editable={editable}
                    ancho="min-w-32"
                >
                    {formatearFecha(factura.fecha_emision)}
                </ValorEditable>
            </TableCell>

            {/* Sale de la emisión: no se edita. En rojo, con los días de
                atraso, mientras falte cobrar algo de una factura vencida. */}
            <TableCell
                className={cn(
                    'whitespace-nowrap tabular-nums',
                    vencida
                        ? 'font-medium text-red-600 dark:text-red-400'
                        : 'text-muted-foreground',
                )}
                title={
                    vencida
                        ? `Vencida hace ${factura.dias_vencida} días`
                        : undefined
                }
            >
                {formatearFecha(factura.fecha_vencimiento)}
                {vencida && (
                    <span className="block text-xs">
                        +{factura.dias_vencida}d
                    </span>
                )}
            </TableCell>

            <TableCell className="whitespace-nowrap tabular-nums">
                <ValorEditable
                    url={url}
                    campo="fecha_pago"
                    valor={factura.fecha_pago}
                    tipo="fecha"
                    editable={editable}
                    ancho="min-w-32"
                    etiqueta="Fecha de pago del neto"
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

            {/* La otra mitad del cobro. Sin detracción (flete bajo el umbral)
                no hay nada que registrar. */}
            <TableCell className="whitespace-nowrap tabular-nums">
                {llevaDetraccion ? (
                    <>
                        <ValorEditable
                            url={url}
                            campo="fecha_detraccion"
                            valor={factura.fecha_detraccion}
                            tipo="fecha"
                            editable={editable}
                            ancho="min-w-32"
                            etiqueta="Fecha del depósito de la detracción"
                        >
                            {factura.fecha_detraccion
                                ? formatearFecha(factura.fecha_detraccion)
                                : null}
                        </ValorEditable>
                        <div className="text-xs text-muted-foreground">
                            <ValorEditable
                                url={url}
                                campo="constancia_detraccion"
                                valor={factura.constancia_detraccion}
                                editable={editable}
                                ancho="min-w-32"
                                etiqueta="Constancia de la detracción"
                                placeholder="constancia"
                            />
                        </div>
                    </>
                ) : (
                    <span className="text-xs text-muted-foreground/60">
                        No aplica
                    </span>
                )}
            </TableCell>

            <TableCell className="max-w-56 text-muted-foreground">
                <ValorEditable
                    url={url}
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

function CeldaVacia() {
    return <TableCell className="text-muted-foreground/40">—</TableCell>;
}

/** Una cifra que calcula el servidor: se lee, no se edita. */
function CeldaCifra({
    valor,
    destacada = false,
}: {
    valor: number | null;
    destacada?: boolean;
}) {
    return (
        <TableCell
            className={cn(
                'text-right whitespace-nowrap tabular-nums',
                destacada ? 'font-semibold' : 'text-muted-foreground',
            )}
        >
            {valor === null ? (
                <span className="text-muted-foreground/40">—</span>
            ) : (
                formatearMonto(valor)
            )}
        </TableCell>
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
