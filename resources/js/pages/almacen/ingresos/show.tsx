import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { ArrowDownToLine, FileText, Gift, Lock, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import articulos from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import ingresos, {
    anular,
    reemplazarComprobante,
} from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { ComprobanteAdjunto } from '@/components/almacen/comprobante-adjunto';
import { Dato } from '@/components/almacen/dato';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { Spinner } from '@/components/ui/spinner';
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
import {
    formatearFecha,
    formatearFechaHora,
    formatearSoles,
} from '@/lib/format';
import type { IngresoDetalle } from '@/types/almacen';

type Props = {
    ingreso: IngresoDetalle;
    puedeAnular: boolean;
    /** El mes de este ingreso sigue abierto: se puede anular. */
    periodoAbierto: boolean;
    puedeReemplazarComprobante: boolean;
    maximoKb: number;
};

/** Un ingreso: el comprobante tal cual y lo que entró al kardex. */
export default function IngresoShow({
    ingreso,
    puedeAnular,
    periodoAbierto,
    puedeReemplazarComprobante,
    maximoKb,
}: Props) {
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
                        {puedeReemplazarComprobante && (
                            <ReemplazarComprobanteDialog
                                ingresoId={ingreso.id}
                                maximoKb={maximoKb}
                                tieneArchivo={ingreso.comprobante !== null}
                            />
                        )}
                        {puedeAnular && periodoAbierto && (
                            <AnularDialog
                                url={anular(ingreso.id).url}
                                titulo={`Anular ${ingreso.numero}`}
                                descripcion="Lo que entró sale del kardex. Si ya se despachó, primero hay que anular esos vales."
                            />
                        )}
                    </>
                }
            />

            {puedeAnular && !periodoAbierto && !ingreso.anulado && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Lock className="size-4" />
                    Su mes ya cerró: no se puede anular.
                </p>
            )}

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
                <Dato titulo={compra ? 'Ingresó al almacén' : 'Fecha'}>
                    {formatearFecha(ingreso.fecha)}
                    {compra && ingreso.fecha_emision && (
                        <span className="block text-xs text-muted-foreground">
                            Comprobante del{' '}
                            {formatearFecha(ingreso.fecha_emision)}
                        </span>
                    )}
                </Dato>
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
                                        href={articulos.show(
                                            detalle.articulo_id,
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {detalle.nombre}
                                    </Link>
                                    <span className="block text-xs text-muted-foreground">
                                        {detalle.codigo}
                                    </span>
                                    {detalle.bonificacion && (
                                        <span className="mt-0.5 inline-flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400">
                                            <Gift className="size-3" />
                                            Bonificación: entra con su valor, no
                                            se pagó
                                        </span>
                                    )}
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
                                            {formatearSoles(
                                                detalle.costo_total,
                                            )}
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
                            {(ingreso.descuento ?? 0) > 0 && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Descuento
                                    </dt>
                                    <dd className="text-right tabular-nums">
                                        −
                                        {formatearMonto(
                                            ingreso.descuento,
                                            ingreso.moneda,
                                        )}
                                    </dd>
                                </>
                            )}
                            {(ingreso.flete ?? 0) > 0 && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Flete
                                    </dt>
                                    <dd className="text-right tabular-nums">
                                        {formatearMonto(
                                            ingreso.flete,
                                            ingreso.moneda,
                                        )}
                                    </dd>
                                </>
                            )}
                            <dt className="text-muted-foreground">Subtotal</dt>
                            <dd className="text-right tabular-nums">
                                {formatearMonto(
                                    ingreso.subtotal,
                                    ingreso.moneda,
                                )}
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

/**
 * Cambiar la foto o el PDF: se subió el equivocado o salió ilegible. No toca
 * el kardex.
 */
function ReemplazarComprobanteDialog({
    ingresoId,
    maximoKb,
    tieneArchivo,
}: {
    ingresoId: number;
    maximoKb: number;
    tieneArchivo: boolean;
}) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, post, processing, errors, reset, progress } =
        useForm<{ comprobante: File | null }>({ comprobante: null });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();
        post(reemplazarComprobante(ingresoId).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <RefreshCw className="size-4" />
                    {tieneArchivo
                        ? 'Reemplazar comprobante'
                        : 'Adjuntar comprobante'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={guardar}>
                    <DialogHeader>
                        <DialogTitle>
                            {tieneArchivo
                                ? 'Reemplazar el comprobante'
                                : 'Adjuntar el comprobante'}
                        </DialogTitle>
                        <DialogDescription>
                            {tieneArchivo
                                ? 'El archivo anterior se borra. El kardex no cambia.'
                                : 'La foto o el PDF del comprobante de esta compra.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <ComprobanteAdjunto
                            archivo={data.comprobante}
                            onCambio={(archivo) =>
                                setData('comprobante', archivo)
                            }
                            maximoKb={maximoKb}
                            error={errors.comprobante}
                        />
                    </div>
                    <DialogFooter>
                        {progress && (
                            <span className="self-center text-xs text-muted-foreground">
                                Subiendo {progress.percentage}%
                            </span>
                        )}
                        <Button
                            type="submit"
                            disabled={processing || data.comprobante === null}
                        >
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
