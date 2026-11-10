import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Check,
    Copy,
    FileDown,
    Paperclip,
    Pencil,
    ShieldAlert,
    ShieldCheck,
    ShoppingCart,
} from 'lucide-react';
import { useState } from 'react';
import ingresosAlmacen from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import compras, {
    anular,
    aprobar,
    confirmarServicio,
    parciales,
    pdf,
    rechazar,
} from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import { edit as editarOrden } from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { CodigoQr } from '@/components/almacen/codigo-qr';
import { Dato } from '@/components/almacen/dato';
import { CampoArchivo } from '@/components/campo-archivo';
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
import { Spinner } from '@/components/ui/spinner';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearCantidad } from '@/lib/almacen';
import {
    formatearFecha,
    formatearFechaHora,
    formatearPlaca,
} from '@/lib/format';
import { TONO_SITUACION } from '@/types/compras';
import type { LineaVale, ValeDetalle } from '@/types/compras';

type Props = {
    vale: ValeDetalle;
    puede: {
        aprobar: boolean;
        corregir: boolean;
        anular: boolean;
        parciales: boolean;
        confirmar_servicio: boolean;
        ingresar: boolean;
        imprimir: boolean;
    };
    /** La cuadrícula del QR de verificación; null si el vale no está aprobado. */
    qr: boolean[][] | null;
    /** Lo pidió quien lo está viendo: no puede aprobarlo. */
    esPropio: boolean;
    maximoKb: number;
};

/**
 * El vale de compra: lo que se pidió, quién lo aprobó y si todavía sirve.
 * Aprobado, se descarga en PDF con su QR; la compra entra al almacén desde
 * acá, ya ligada al vale.
 */
export default function CompraShow({
    vale,
    puede,
    qr,
    esPropio,
    maximoKb,
}: Props) {
    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Compras', href: compras.index().url },
            { title: vale.numero, href: compras.show(vale.id).url },
        ),
    });

    const monto = (valor: number) =>
        `${vale.simbolo} ${valor.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;

    const aprobacion = useForm({});
    const erroresAprobacion = aprobacion.errors as Record<
        string,
        string | undefined
    >;
    const hayArticulos = vale.detalles.some((d) => !d.es_servicio);

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={vale.numero} />

            <EncabezadoPantalla
                titulo={`Vale de compra ${vale.numero}`}
                descripcion={`${vale.tipo_label} · ${vale.proveedor}`}
                icono={ShoppingCart}
                acciones={
                    <>
                        {puede.imprimir && (
                            <Button variant="outline" asChild>
                                <a href={pdf(vale.id).url}>
                                    <FileDown className="size-4" />
                                    PDF
                                </a>
                            </Button>
                        )}
                        {puede.corregir && (
                            <Button variant="outline" asChild>
                                <Link href={compras.edit(vale.id)}>
                                    <Pencil className="size-4" />
                                    Corregir
                                </Link>
                            </Button>
                        )}
                        {puede.anular && (
                            <AnularDialog
                                url={anular(vale.id).url}
                                titulo={`Anular ${vale.numero}`}
                                descripcion="El vale deja de servir para comprar. Queda a la vista con el motivo, y quien lo verifique verá que está anulado."
                            />
                        )}
                    </>
                }
            />

            <div className="flex flex-wrap items-center gap-3">
                <StatusBadge
                    label={vale.situacion_label}
                    tone={TONO_SITUACION[vale.situacion]}
                />
                {vale.vence_el && (
                    <span className="text-sm text-muted-foreground">
                        Vale hasta el {formatearFecha(vale.vence_el)}
                    </span>
                )}
                {vale.firma_valida === true && (
                    <span className="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                        <ShieldCheck className="size-4" />
                        Firma válida
                    </span>
                )}
                {vale.firma_valida === false && (
                    <span className="flex items-center gap-1.5 text-sm font-medium text-destructive">
                        <ShieldAlert className="size-4" />
                        No coincide con lo que se firmó: fue modificado
                    </span>
                )}
            </div>

            {vale.situacion === 'rechazado' && (
                <Aviso tono="rojo">
                    Rechazado por {vale.rechazado_por ?? '—'}:{' '}
                    {vale.motivo_rechazo}. Se puede corregir y volver a pedir.
                </Aviso>
            )}
            {vale.situacion === 'anulado' && (
                <Aviso tono="rojo">
                    Anulado por {vale.anulado_por ?? '—'}:{' '}
                    {vale.motivo_anulacion}
                </Aviso>
            )}

            {puede.aprobar && (
                <section className="flex flex-col gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-5">
                    <h2 className="text-sm font-semibold">
                        Espera tu aprobación
                    </h2>
                    {esPropio ? (
                        <p className="text-sm text-muted-foreground">
                            Esta compra la pediste tú: tiene que aprobarla otra
                            persona.
                        </p>
                    ) : (
                        <>
                            <p className="text-sm text-muted-foreground">
                                Al aprobar, el sistema firma el vale por{' '}
                                <strong className="text-foreground">
                                    {monto(vale.total)}
                                </strong>{' '}
                                a {vale.proveedor} y vale 5 días. Si después
                                cambia el monto, vuelve a ti.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    disabled={aprobacion.processing}
                                    onClick={() =>
                                        aprobacion.post(aprobar(vale.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {aprobacion.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Check className="size-4" />
                                    )}
                                    Aprobar la compra
                                </Button>
                                <AnularDialog
                                    url={rechazar(vale.id).url}
                                    titulo={`Rechazar ${vale.numero}`}
                                    descripcion="Quien la pidió verá el motivo y podrá corregirla."
                                    etiqueta="Rechazar"
                                />
                            </div>
                            <InputError message={erroresAprobacion.firma} />
                        </>
                    )}
                </section>
            )}

            <section className="grid gap-6 rounded-xl border bg-card p-5 md:grid-cols-[1fr_auto]">
                <dl className="grid content-start gap-4 text-sm sm:grid-cols-2">
                    <Dato titulo="Proveedor">
                        {vale.proveedor}
                        {vale.proveedor_ruc && (
                            <span className="block text-xs text-muted-foreground">
                                RUC {vale.proveedor_ruc}
                            </span>
                        )}
                    </Dato>
                    <Dato titulo="Total a pagar">
                        <span className="text-base font-semibold tabular-nums">
                            {monto(vale.total)}
                        </span>
                    </Dato>
                    <Dato titulo="Para">
                        {vale.placa ? formatearPlaca(vale.placa) : 'Almacén'}
                        {vale.orden && (
                            <Link
                                href={editarOrden(vale.orden.id)}
                                className="ml-2 underline underline-offset-2"
                            >
                                {vale.orden.numero}
                            </Link>
                        )}
                    </Dato>
                    <Dato titulo="Pidió">
                        {vale.solicitado_por ?? '—'}
                        {vale.solicitado && (
                            <span className="block text-xs text-muted-foreground">
                                {formatearFechaHora(vale.solicitado)}
                            </span>
                        )}
                    </Dato>
                    <Dato titulo="Aprobó">
                        {vale.aprobado_por ?? '—'}
                        {vale.aprobado_at && (
                            <span className="block text-xs text-muted-foreground">
                                {formatearFechaHora(vale.aprobado_at)}
                            </span>
                        )}
                    </Dato>
                    <Dato titulo="Verificaciones">
                        {vale.consultas === 0
                            ? 'Nadie lo verificó todavía'
                            : `${vale.consultas} ${vale.consultas === 1 ? 'vez' : 'veces'}`}
                    </Dato>
                    <div className="sm:col-span-2">
                        <Dato titulo="Para qué es">{vale.motivo}</Dato>
                    </div>
                    {(vale.sustento_precio || vale.cotizaciones.length > 0) && (
                        <div className="sm:col-span-2">
                            <Dato titulo="Sustento del precio">
                                {vale.sustento_precio}
                                {vale.cotizaciones.length > 0 && (
                                    <ul className="mt-1 flex flex-col gap-1">
                                        {vale.cotizaciones.map((archivo) => (
                                            <li key={archivo.id}>
                                                <a
                                                    href={archivo.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1.5 underline underline-offset-2"
                                                >
                                                    <Paperclip className="size-3.5" />
                                                    {archivo.nombre}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Dato>
                        </div>
                    )}
                </dl>

                {qr && (
                    <div className="flex flex-col items-center gap-2 md:w-48">
                        <CodigoQr
                            celdas={qr}
                            className="size-40 rounded-md border"
                            titulo={`QR de verificación de ${vale.numero}`}
                        />
                        <CodigoCopiable codigo={vale.codigo_verificacion} />
                        <a
                            href={vale.url_verificacion}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs text-muted-foreground underline underline-offset-2"
                        >
                            Ver como lo ve el proveedor
                        </a>
                    </div>
                )}
            </section>

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                    Lo que autoriza
                </h2>
                <div className="overflow-x-auto rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Descripción</TableHead>
                                <TableHead className="text-right">
                                    Cantidad
                                </TableHead>
                                <TableHead className="text-right">
                                    Precio
                                </TableHead>
                                <TableHead className="text-right">
                                    Importe
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {vale.detalles.map((linea) => (
                                <TableRow key={linea.id}>
                                    <TableCell>
                                        {linea.descripcion}
                                        <span className="block text-xs text-muted-foreground">
                                            {linea.es_servicio
                                                ? 'Servicio de un tercero'
                                                : linea.codigo}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {formatearCantidad(
                                            linea.cantidad,
                                            linea.unidad,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {monto(linea.precio_unitario)}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {monto(linea.total)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {linea.es_servicio &&
                                            (linea.atendido_at ? (
                                                <span className="text-xs text-emerald-600 dark:text-emerald-400">
                                                    Atendido el{' '}
                                                    {formatearFecha(
                                                        linea.atendido_at,
                                                    )}
                                                </span>
                                            ) : (
                                                puede.confirmar_servicio && (
                                                    <ConfirmarServicioDialog
                                                        valeId={vale.id}
                                                        linea={linea}
                                                        maximoKb={maximoKb}
                                                    />
                                                )
                                            ))}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </section>

            {(vale.ingresos.length > 0 || (puede.ingresar && hayArticulos)) && (
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                            Compras con este vale
                        </h2>
                        {vale.recibido > 0 && vale.saldo > 0 && (
                            <span className="text-sm text-muted-foreground">
                                Quedan {monto(vale.saldo)}
                            </span>
                        )}
                    </div>
                    {vale.ingresos.length > 0 && (
                        <ul className="divide-y rounded-xl border bg-card text-sm">
                            {vale.ingresos.map((ingreso) => (
                                <li
                                    key={ingreso.id}
                                    className="flex items-center justify-between gap-3 px-4 py-2.5"
                                >
                                    <Link
                                        href={ingresosAlmacen.show(ingreso.id)}
                                        className={
                                            ingreso.anulado
                                                ? 'text-muted-foreground line-through'
                                                : 'underline underline-offset-2'
                                        }
                                    >
                                        {ingreso.numero} · {ingreso.documento}
                                    </Link>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatearFecha(ingreso.fecha)} ·{' '}
                                        {monto(ingreso.total)}
                                        {ingreso.anulado && ' · anulado'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {puede.ingresar && hayArticulos && (
                        <Button asChild className="self-start">
                            <Link
                                href={ingresosAlmacen.create.url({
                                    query: { vale: vale.id },
                                })}
                            >
                                Registrar la compra con este vale
                            </Link>
                        </Button>
                    )}
                </section>
            )}

            {vale.permite_parciales !== null && puede.parciales && (
                <section className="flex flex-col gap-2 rounded-xl border border-dashed p-5 text-sm">
                    <h2 className="font-semibold">Entregas parciales</h2>
                    <p className="text-muted-foreground">
                        {vale.permite_parciales
                            ? 'Este vale admite varias compras hasta completar su monto.'
                            : 'Un vale sirve para una sola compra por el monto exacto. Solo si el proveedor va a entregar en partes, actívalo acá.'}
                    </p>
                    <Button
                        variant="outline"
                        className="self-start"
                        onClick={() =>
                            router.post(
                                parciales(vale.id).url,
                                {
                                    permite_parciales: !vale.permite_parciales,
                                },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {vale.permite_parciales
                            ? 'Volver a una sola compra'
                            : 'Permitir entregas parciales'}
                    </Button>
                </section>
            )}
        </div>
    );
}

function Aviso({
    tono,
    children,
}: {
    tono: 'rojo';
    children: React.ReactNode;
}) {
    return (
        <p
            className={
                tono === 'rojo'
                    ? 'rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive'
                    : ''
            }
        >
            {children}
        </p>
    );
}

/** El código que se escribe a mano, con su botón para copiarlo. */
function CodigoCopiable({ codigo }: { codigo: string }) {
    const [copiado, setCopiado] = useState(false);

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(codigo);
            setCopiado(true);
            window.setTimeout(() => setCopiado(false), 2000);
        } catch {
            // Sin permiso de portapapeles el código igual se puede leer.
        }
    };

    return (
        <button
            type="button"
            onClick={copiar}
            className="flex items-center gap-2 rounded-md border px-2.5 py-1.5 font-mono text-sm font-semibold tracking-wider hover:bg-accent"
            aria-label="Copiar el código de verificación"
        >
            {codigo}
            {copiado ? (
                <Check className="size-3.5 text-emerald-600" />
            ) : (
                <Copy className="size-3.5 text-muted-foreground" />
            )}
        </button>
    );
}

/**
 * El tercero ya hizo el servicio: con su comprobante, pasa a ser costo de la
 * orden de trabajo.
 */
function ConfirmarServicioDialog({
    valeId,
    linea,
    maximoKb,
}: {
    valeId: number;
    linea: LineaVale;
    maximoKb: number;
}) {
    const [abierto, setAbierto] = useState(false);
    const [preparando, setPreparando] = useState(false);
    const { data, setData, post, processing, errors, progress, reset } =
        useForm<{ comprobante: File | null }>({ comprobante: null });

    const confirmar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();

        post(confirmarServicio({ vale: valeId, detalle: linea.id }).url, {
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
                <Button variant="outline" size="sm">
                    Confirmar servicio
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={confirmar}>
                    <DialogHeader>
                        <DialogTitle>Confirmar el servicio</DialogTitle>
                        <DialogDescription>
                            {linea.descripcion}. Adjunta la factura o el recibo
                            del tercero: desde acá cuenta en el costo de la
                            orden.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <CampoArchivo
                            archivos={
                                data.comprobante ? [data.comprobante] : []
                            }
                            onArchivos={(lista) =>
                                setData('comprobante', lista[0] ?? null)
                            }
                            perfil="comprobante"
                            maximoKb={maximoKb}
                            progreso={progress?.percentage ?? null}
                            error={errors.comprobante}
                            onPreparando={setPreparando}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || preparando}
                        >
                            {processing && <Spinner />}
                            Confirmar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
