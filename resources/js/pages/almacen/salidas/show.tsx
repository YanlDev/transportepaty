import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { ArrowUpFromLine, Printer, Undo2 } from 'lucide-react';
import { useState } from 'react';
import articulos from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import ingresos from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import salidas, {
    anular,
    devolver,
} from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { Dato } from '@/components/almacen/dato';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatearCantidad, formatearUnitario } from '@/lib/almacen';
import {
    formatearFecha,
    formatearFechaHora,
    formatearPlaca,
    formatearSoles,
} from '@/lib/format';
import type { SalidaDetalle } from '@/types/almacen';

type Props = {
    salida: SalidaDetalle;
    puedeAnular: boolean;
    periodoAbierto: boolean;
    puedeDevolver: boolean;
    hoy: string;
};

/** El vale: qué salió, a dónde y cuánto costó. Se imprime para firmarlo. */
export default function SalidaShow({
    salida,
    puedeAnular,
    puedeDevolver,
    periodoAbierto,
    hoy,
}: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const pendiente = salida.detalles.some((d) => d.cantidad - d.devuelto > 0);

    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Vales de salida', href: salidas.index().url },
            { title: salida.numero, href: salidas.show(salida.id).url },
        ),
    });

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={salida.numero} />

            <EncabezadoPantalla
                titulo={`Vale ${salida.numero}`}
                descripcion={`${salida.destino_label}${salida.placa ? ` · ${formatearPlaca(salida.placa)}` : ''}`}
                icono={ArrowUpFromLine}
                className="print:hidden"
                acciones={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" />
                            Imprimir
                        </Button>
                        {puedeDevolver && pendiente && (
                            <DevolucionDialog salida={salida} hoy={hoy} />
                        )}
                        {puedeAnular && periodoAbierto && (
                            <AnularDialog
                                url={anular(salida.id).url}
                                titulo={`Anular ${salida.numero}`}
                                descripcion="Lo despachado vuelve al stock y el vale sale del kardex. Para lo que solo volvió en parte, usa «Devolver»."
                            />
                        )}
                    </>
                }
            />

            <h1 className="hidden text-xl font-semibold print:block">
                Vale de salida {salida.numero}
            </h1>

            {salida.anulado && (
                <p className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                    Anulado
                    {salida.anulado_por && ` por ${salida.anulado_por}`}
                    {salida.anulado_at &&
                        ` el ${formatearFechaHora(salida.anulado_at)}`}
                    {salida.motivo_anulacion && `: ${salida.motivo_anulacion}`}
                </p>
            )}

            <dl className="grid grid-cols-2 gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-4">
                <Dato titulo="Fecha">{formatearFecha(salida.fecha)}</Dato>
                <Dato titulo="Destino">
                    {salida.destino_label}
                    {salida.placa && (
                        <span className="block font-medium">
                            {formatearPlaca(salida.placa)}
                        </span>
                    )}
                </Dato>
                <Dato titulo="Recibió">{salida.entregado_a ?? '—'}</Dato>
                <Dato titulo="Emitió">
                    {salida.usuario ?? '—'}
                    {salida.registrado && (
                        <span className="block text-xs text-muted-foreground">
                            {formatearFechaHora(salida.registrado)}
                        </span>
                    )}
                </Dato>
                {salida.orden && (
                    <div className="col-span-full">
                        <Dato
                            titulo={`Orden de trabajo ${salida.orden.id} · ${salida.orden.estado}`}
                        >
                            {salida.orden.trabajo}
                        </Dato>
                    </div>
                )}
                {salida.observacion && (
                    <div className="col-span-full">
                        <Dato titulo="Observación">{salida.observacion}</Dato>
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
                            <TableHead className="text-right">
                                Devuelto
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
                        {salida.detalles.map((detalle) => (
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
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatearCantidad(
                                        detalle.cantidad,
                                        detalle.unidad,
                                    )}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                    {detalle.devuelto > 0
                                        ? formatearCantidad(detalle.devuelto)
                                        : '—'}
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
                <p className="text-right text-sm">
                    Costo del vale:{' '}
                    <strong className="tabular-nums">
                        {formatearSoles(salida.costo_total)}
                    </strong>
                </p>
            )}

            {salida.devoluciones.length > 0 && (
                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Devoluciones
                    </h2>
                    <ul className="flex flex-wrap gap-2">
                        {salida.devoluciones.map((devolucion) => (
                            <li key={devolucion.id}>
                                <Link
                                    href={ingresos.show(devolucion.id)}
                                    className="flex items-center gap-2 rounded-lg border bg-card px-3 py-1.5 text-sm hover:bg-accent/50"
                                >
                                    {devolucion.numero} ·{' '}
                                    {formatearFecha(devolucion.fecha)}
                                    {devolucion.costo_total !== null &&
                                        ` · ${formatearSoles(devolucion.costo_total)}`}
                                    {devolucion.anulado && (
                                        <EstadoDocumento tono="anulado">
                                            Anulada
                                        </EstadoDocumento>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <div className="mt-16 hidden grid-cols-2 gap-16 text-center text-sm print:grid">
                <div className="border-t pt-2">Entregó (almacén)</div>
                <div className="border-t pt-2">
                    Recibió{salida.entregado_a && `: ${salida.entregado_a}`}
                </div>
            </div>
        </div>
    );
}

/** Lo que no se usó vuelve al stock, al mismo costo con que salió. */
function DevolucionDialog({
    salida,
    hoy,
}: {
    salida: SalidaDetalle;
    hoy: string;
}) {
    const [abierto, setAbierto] = useState(false);
    const lineas = salida.detalles.filter((d) => d.cantidad - d.devuelto > 0);
    const { data, setData, post, processing, errors, reset, transform } =
        useForm({
            fecha: hoy,
            observacion: '',
            cantidades: Object.fromEntries(
                lineas.map((d) => [d.id, '']),
            ) as Record<number, string>,
        });
    const errores = errors as Record<string, string | undefined>;

    transform((datos) => ({
        fecha: datos.fecha,
        observacion: datos.observacion,
        detalles: Object.entries(datos.cantidades)
            .filter(([, cantidad]) => Number(cantidad) > 0)
            .map(([id, cantidad]) => ({
                detalle_salida_id: Number(id),
                cantidad,
            })),
    }));

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(devolver(salida.id).url, {
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
                    <Undo2 className="size-4" />
                    Devolver
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={guardar}>
                    <DialogHeader>
                        <DialogTitle>
                            Devolución del {salida.numero}
                        </DialogTitle>
                        <DialogDescription>
                            Anota solo lo que vuelve sin usar.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 py-4">
                        {lineas.map((detalle) => (
                            <div
                                key={detalle.id}
                                className="grid grid-cols-[1fr_7rem] items-center gap-2"
                            >
                                <Label htmlFor={`devolver-${detalle.id}`}>
                                    {detalle.nombre}
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        Salió{' '}
                                        {formatearCantidad(
                                            detalle.cantidad - detalle.devuelto,
                                            detalle.unidad,
                                        )}
                                    </span>
                                </Label>
                                <div>
                                    <Input
                                        id={`devolver-${detalle.id}`}
                                        inputMode="decimal"
                                        value={data.cantidades[detalle.id]}
                                        onChange={(e) =>
                                            setData('cantidades', {
                                                ...data.cantidades,
                                                [detalle.id]: e.target.value,
                                            })
                                        }
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        ))}
                        <div className="grid gap-1.5">
                            <Label htmlFor="devolucion-fecha">Fecha</Label>
                            <Input
                                id="devolucion-fecha"
                                type="date"
                                max={hoy}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                            <InputError message={errores.fecha} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="devolucion-obs">Observación</Label>
                            <Input
                                id="devolucion-obs"
                                value={data.observacion}
                                onChange={(e) =>
                                    setData('observacion', e.target.value)
                                }
                            />
                        </div>
                        <InputError
                            message={Object.entries(errores)
                                .filter(([clave]) =>
                                    clave.startsWith('detalles'),
                                )
                                .map(([, mensaje]) => mensaje)
                                .join(' ')}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Registrar devolución
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
