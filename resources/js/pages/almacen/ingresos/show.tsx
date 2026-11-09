import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowDownToLine, FileText } from 'lucide-react';
import articulos from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import ingresos, {
    anular,
} from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { Dato } from '@/components/almacen/dato';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import {
    formatearCantidad,
    formatearMonto,
    formatearUnitario,
} from '@/lib/almacen';
import { formatearFecha, formatearFechaHora, formatearSoles } from '@/lib/format';
import type { IngresoDetalle } from '@/types/almacen';

type Props = {
    ingreso: IngresoDetalle;
    puedeAnular: boolean;
};

/** Un ingreso: el comprobante tal cual y lo que entró al kardex. */
export default function IngresoShow({ ingreso, puedeAnular }: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const compra = ingreso.tipo === 'compra';

    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Ingresos', href: ingresos.index().url },
            { title: ingreso.numero, href: ingresos.show(ingreso.id).url },
        ),
    });

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={ingreso.numero} />

            <EncabezadoPantalla
                titulo={`${ingreso.tipo_label} ${ingreso.numero}`}
                descripcion={
                    compra
                        ? `${ingreso.comprobante_tipo ?? ''} ${ingreso.serie_numero ?? ''} · ${ingreso.proveedor ?? ''}`
                        : `Vuelve del vale ${ingreso.salida ?? ''}`
                }
                icono={ArrowDownToLine}
                acciones={
                    <>
                        {ingreso.comprobante && (
                            <Button variant="outline" asChild>
                                <a
                                    href={ingreso.comprobante.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText className="size-4" />
                                    Ver comprobante
                                </a>
                            </Button>
                        )}
                        {puedeAnular && (
                            <AnularDialog
                                url={anular(ingreso.id).url}
                                titulo={`Anular ${ingreso.numero}`}
                                descripcion="Lo que entró sale del kardex. Si ya se despachó, primero hay que anular esos vales."
                            />
                        )}
                    </>
                }
            />

            {ingreso.anulado && (
                <p className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                    Anulado
                    {ingreso.anulado_por && ` por ${ingreso.anulado_por}`}
                    {ingreso.anulado_at &&
                        ` el ${formatearFechaHora(ingreso.anulado_at)}`}
                    {ingreso.motivo_anulacion &&
                        `: ${ingreso.motivo_anulacion}`}
                </p>
            )}

            <dl className="grid grid-cols-2 gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-4">
                <Dato titulo="Fecha">{formatearFecha(ingreso.fecha)}</Dato>
                {compra ? (
                    <>
                        <Dato titulo="Proveedor">
                            {ingreso.proveedor_detalle?.razon_social ?? '—'}
                            {ingreso.proveedor_detalle?.ruc && (
                                <span className="block text-xs text-muted-foreground">
                                    RUC {ingreso.proveedor_detalle.ruc}
                                </span>
                            )}
                        </Dato>
                        <Dato titulo="Moneda">
                            {ingreso.moneda}
                            {ingreso.tipo_cambio &&
                                ` · TC ${ingreso.tipo_cambio}`}
                        </Dato>
                        <Dato titulo="Precios">
                            {ingreso.precios_con_igv ? 'Con IGV' : 'Sin IGV'}
                        </Dato>
                    </>
                ) : (
                    <Dato titulo="Vale">
                        {ingreso.salida_id ? (
                            <Link
                                href={salidas.show(ingreso.salida_id)}
                                className="text-primary hover:underline"
                            >
                                {ingreso.salida}
                            </Link>
                        ) : (
                            '—'
                        )}
                    </Dato>
                )}
                <Dato titulo="Registró">
                    {ingreso.usuario ?? '—'}
                    {ingreso.registrado && (
                        <span className="block text-xs text-muted-foreground">
                            {formatearFechaHora(ingreso.registrado)}
                        </span>
                    )}
                </Dato>
                {ingreso.observacion && (
                    <div className="col-span-full">
                        <Dato titulo="Observación">{ingreso.observacion}</Dato>
                    </div>
                )}
            </dl>

            <div className="overflow-x-auto rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Artículo</TableHead>
                            <TableHead className="text-right">
                                Cantidad
                            </TableHead>
                            {veCostos && compra && (
                                <TableHead className="text-right">
                                    P. unit. comprobante
                                </TableHead>
                            )}
                            <TableHead className="text-right">
                                Entra al almacén
                            </TableHead>
                            {veCostos && (
                                <>
                                    <TableHead className="text-right">
                                        Costo unit.
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Costo total
                                    </TableHead>
                                </>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {ingreso.detalles.map((detalle) => (
                            <TableRow key={detalle.id}>
                                <TableCell>
                                    <Link
                                        href={articulos.show(detalle.articulo_id)}
                                        className="font-medium hover:underline"
                                    >
                                        {detalle.nombre}
                                    </Link>
                                    <span className="block text-xs text-muted-foreground">
                                        {detalle.codigo}
                                    </span>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatearCantidad(
                                        detalle.cantidad,
                                        detalle.unidad_compra ?? detalle.unidad,
                                    )}
                                </TableCell>
                                {veCostos && compra && (
                                    <TableCell className="text-right tabular-nums">
                                        {formatearMonto(
                                            detalle.precio_unitario,
                                            ingreso.moneda,
                                        )}
                                    </TableCell>
                                )}
                                <TableCell className="text-right tabular-nums">
                                    {formatearCantidad(
                                        detalle.cantidad_base,
                                        detalle.unidad,
                                    )}
                                </TableCell>
                                {veCostos && (
                                    <>
                                        <TableCell className="text-right tabular-nums">
                                            {formatearUnitario(
                                                detalle.costo_unitario,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatearSoles(detalle.costo_total)}
                                        </TableCell>
                                    </>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {veCostos && (
                <dl className="ml-auto grid w-full max-w-xs grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    {compra && (
                        <>
                            <dt className="text-muted-foreground">Subtotal</dt>
                            <dd className="text-right tabular-nums">
                                {formatearMonto(ingreso.subtotal, ingreso.moneda)}
                            </dd>
                            <dt className="text-muted-foreground">IGV</dt>
                            <dd className="text-right tabular-nums">
                                {formatearMonto(ingreso.igv, ingreso.moneda)}
                            </dd>
                            <dt className="font-medium">Total comprobante</dt>
                            <dd className="text-right font-medium tabular-nums">
                                {formatearMonto(ingreso.total, ingreso.moneda)}
                            </dd>
                        </>
                    )}
                    <dt className="text-muted-foreground">Entró al kardex</dt>
                    <dd className="text-right tabular-nums">
                        {formatearSoles(ingreso.costo_total)}
                    </dd>
                </dl>
            )}

            {ingreso.anulado || (
                <div>
                    <EstadoDocumento tono="vigente">
                        En el kardex
                    </EstadoDocumento>
                </div>
            )}
        </div>
    );
}
