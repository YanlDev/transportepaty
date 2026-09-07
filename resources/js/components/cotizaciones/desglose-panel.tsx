import { Spinner } from '@/components/ui/spinner';
import { formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { DesgloseCotizacion } from '@/types/fleet';

/**
 * La tarifa abierta línea por línea, separando lo directo de lo indirecto.
 *
 * La separación no es contable por gusto: un precio que no cubre los costos
 * directos pierde plata en cada vuelta, mientras que uno que los cubre pero
 * aporta poco a la estructura puede tener sentido para llenar un retorno que
 * si no viaja vacío. Es la información que decide hasta dónde se puede bajar
 * en una negociación.
 */
export function DesglosePanel({
    desglose,
    km,
    dias,
    igvPct,
    calculando,
    vacio,
}: {
    desglose: DesgloseCotizacion | null;
    km: number;
    dias: number;
    igvPct: number;
    calculando?: boolean;
    vacio?: string;
}) {
    const directos = desglose?.desglose.componentes.filter(
        (linea) => linea.naturaleza === 'directo',
    );
    const indirectos = desglose?.desglose.componentes.filter(
        (linea) => linea.naturaleza === 'indirecto',
    );

    return (
        <section className="rounded-xl border border-border bg-muted/40 p-5">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h2 className="text-sm font-semibold text-foreground">
                        Desglose
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        El cliente solo ve el total. Esto es para decidir el
                        precio.
                    </p>
                </div>
                {calculando && <Spinner />}
            </div>

            {!desglose ? (
                <p className="text-sm text-muted-foreground">
                    {vacio ?? 'Completá kilómetros y días para ver la tarifa.'}
                </p>
            ) : (
                <div className="flex flex-col gap-5">
                    <Grupo
                        titulo="Costos directos"
                        descripcion="Se le pueden atribuir a este viaje."
                        subtotal={desglose.total_directo}
                    >
                        {directos?.map((linea) => (
                            <Linea
                                key={linea.nombre}
                                nombre={linea.nombre}
                                detalle={detalleTasa(
                                    linea.tasa,
                                    linea.tipo,
                                    km,
                                    dias,
                                )}
                                importe={linea.importe}
                                participacion={linea.participacion_pct}
                            />
                        ))}

                        {desglose.desglose.ruta
                            .filter((linea) => linea.importe > 0)
                            .map((linea) => (
                                <Linea
                                    key={linea.campo}
                                    nombre={linea.nombre}
                                    detalle="propio del tramo"
                                    importe={linea.importe}
                                    participacion={linea.participacion_pct}
                                />
                            ))}
                    </Grupo>

                    {indirectos && indirectos.length > 0 && (
                        <Grupo
                            titulo="Costos indirectos"
                            descripcion="Estructura que este viaje ayuda a pagar."
                            subtotal={desglose.total_indirecto}
                        >
                            {indirectos.map((linea) => (
                                <Linea
                                    key={linea.nombre}
                                    nombre={linea.nombre}
                                    detalle={detalleTasa(
                                        linea.tasa,
                                        linea.tipo,
                                        km,
                                        dias,
                                    )}
                                    importe={linea.importe}
                                    participacion={linea.participacion_pct}
                                />
                            ))}
                        </Grupo>
                    )}

                    <dl className="flex flex-col gap-1.5 border-t border-border pt-3 text-sm">
                        <Total
                            etiqueta="Costo operativo"
                            valor={desglose.costo_operativo}
                        />
                        <div className="flex justify-between text-xs text-muted-foreground">
                            <dt>Costo por kilómetro</dt>
                            <dd className="font-mono tabular-nums">
                                {formatearSoles(costoPorKm(desglose, km))}
                            </dd>
                        </div>
                        <Total etiqueta="Margen" valor={desglose.margen} />
                        <Total
                            etiqueta="Subtotal"
                            valor={desglose.subtotal}
                            destacado
                        />
                        <Total
                            etiqueta={`IGV (${(igvPct * 100).toFixed(0)}%)`}
                            valor={desglose.igv}
                        />
                        <Total
                            etiqueta="Total"
                            valor={desglose.total}
                            destacado
                        />
                    </dl>
                </div>
            )}
        </section>
    );
}

function Grupo({
    titulo,
    descripcion,
    subtotal,
    children,
}: {
    titulo: string;
    descripcion: string;
    subtotal: number;
    children: React.ReactNode;
}) {
    return (
        <div>
            <div className="mb-2">
                <h3 className="text-xs font-semibold tracking-wide text-foreground uppercase">
                    {titulo}
                </h3>
                <p className="text-xs text-muted-foreground">{descripcion}</p>
            </div>

            <dl className="flex flex-col gap-1.5">{children}</dl>

            <div className="mt-2 flex justify-between border-t border-border pt-2 text-sm font-medium">
                <span className="text-muted-foreground">Subtotal</span>
                <span className="font-mono text-foreground tabular-nums">
                    {formatearSoles(subtotal)}
                </span>
            </div>
        </div>
    );
}

function Linea({
    nombre,
    detalle,
    importe,
    participacion,
}: {
    nombre: string;
    detalle: string;
    importe: number;
    participacion: number;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="min-w-0 flex-1">
                <span className="text-foreground">{nombre}</span>{' '}
                <span className="text-xs text-muted-foreground">{detalle}</span>
            </dt>
            <dd className="flex shrink-0 items-baseline gap-3">
                <span className="font-mono text-foreground tabular-nums">
                    {formatearSoles(importe)}
                </span>
                <span className="w-12 text-right font-mono text-xs text-muted-foreground tabular-nums">
                    {(participacion * 100).toFixed(1)}%
                </span>
            </dd>
        </div>
    );
}

function Total({
    etiqueta,
    valor,
    destacado,
}: {
    etiqueta: string;
    valor: number;
    destacado?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex justify-between',
                destacado && 'font-semibold text-foreground',
            )}
        >
            <dt>{etiqueta}</dt>
            <dd className="font-mono tabular-nums">{formatearSoles(valor)}</dd>
        </div>
    );
}

function detalleTasa(
    tasa: number,
    tipo: string,
    km: number,
    dias: number,
): string {
    const unidades = tipo === 'fijo_dia' ? dias : km;
    const etiqueta = tipo === 'fijo_dia' ? 'días' : 'km';
    const decimales = tipo === 'fijo_dia' ? 2 : 4;

    return `S/ ${tasa.toFixed(decimales)} × ${unidades.toLocaleString('es-PE')} ${etiqueta}`;
}

function costoPorKm(desglose: DesgloseCotizacion, km: number): number {
    if (
        'costo_por_km' in desglose &&
        typeof desglose.costo_por_km === 'number'
    ) {
        return desglose.costo_por_km;
    }

    return km > 0 ? desglose.costo_operativo / km : 0;
}
