import { Link, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    CircleCheck,
    PackagePlus,
    ShoppingCart,
    Stamp,
} from 'lucide-react';
import type { ReactNode } from 'react';
import ingresos from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import compras from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import { pasarAlCatalogo } from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { PorPagar } from '@/components/almacen/por-pagar';
import { Placa } from '@/components/placa';
import { Button } from '@/components/ui/button';
import { formatearCantidad } from '@/lib/almacen';
import { cn } from '@/lib/utils';
import type { PedidoPorPagar } from '@/types/compras';

type Entrega = {
    id: number;
    numero: string;
    placa: string;
    urgente: boolean;
    mecanico: string | null;
    lineas: {
        descripcion: string;
        unidad: string | null;
        pendiente: number;
        hay: number;
    }[];
    /** Hay stock de al menos una línea. */
    se_puede: boolean;
};

type PorComprar = {
    id: number;
    numero: string;
    placa: string;
    urgente: boolean;
    lineas: {
        repuesto_id: number;
        descripcion: string;
        unidad: string | null;
        falta: number;
        en_catalogo: boolean;
    }[];
};

type Pedido = {
    id: number;
    numero: string;
    proveedor: string;
    total: number | null;
    moneda: string;
    emergencia: boolean;
    puede_aprobar: boolean;
};

export type PorHacerAlmacen = {
    /** Null: no le toca a quien entra. */
    entregas: Entrega[] | null;
    por_comprar: PorComprar[] | null;
    por_aprobar: Pedido[] | null;
    por_recibir: Pedido[] | null;
    /** Lo que ya entró y espera su comprobante. */
    sin_comprobante:
        | { id: number; numero: string; proveedor: string; fecha: string }[]
        | null;
    /** Lo que se le debe a proveedores; null si no anota pagos. */
    por_pagar: PedidoPorPagar[] | null;
    puede_catalogar: boolean;
};

/**
 * Lo que hay que hacer hoy en el almacén, según quien entra: entregarle a
 * las órdenes lo que tienen aprobado, comprar lo que falta, aprobar los
 * pedidos y recibir lo comprado. Cada tarjeta lleva al paso con todo ya
 * elegido.
 */
export function PorHacer({ porHacer }: { porHacer: PorHacerAlmacen }) {
    const { entregas, por_comprar, por_aprobar, por_recibir } = porHacer;
    const total =
        (porHacer.por_pagar?.length ?? 0) +
        (porHacer.sin_comprobante?.length ?? 0) +
        (entregas?.length ?? 0) +
        (por_comprar?.length ?? 0) +
        (por_aprobar?.length ?? 0) +
        (por_recibir?.length ?? 0);

    if (
        entregas === null &&
        por_comprar === null &&
        por_aprobar === null &&
        por_recibir === null &&
        porHacer.por_pagar === null
    ) {
        return null;
    }

    if (total === 0) {
        return (
            <p className="flex items-center gap-2 rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                <CircleCheck className="size-5 text-emerald-600" />
                No hay nada pendiente: ninguna orden espera repuestos ni hay
                pedidos de compra por atender.
            </p>
        );
    }

    return (
        <>
            {porHacer.por_pagar !== null && (
                <PorPagar pedidos={porHacer.por_pagar} />
            )}
            <Tarjetas porHacer={porHacer} />
        </>
    );
}

function Tarjetas({ porHacer }: { porHacer: PorHacerAlmacen }) {
    const { entregas, por_comprar, por_aprobar, por_recibir } = porHacer;

    return (
        <section className="grid gap-3 lg:grid-cols-2">
            {por_aprobar !== null && por_aprobar.length > 0 && (
                <Grupo
                    titulo="Pedidos de compra por aprobar"
                    icono={<Stamp className="size-4" />}
                    cantidad={por_aprobar.length}
                    tono="ambar"
                >
                    {por_aprobar.map((pedido) => (
                        <FilaPedido
                            key={pedido.id}
                            pedido={pedido}
                            accion={
                                pedido.puede_aprobar
                                    ? 'Revisar y aprobar'
                                    : 'Ver'
                            }
                            href={compras.show(pedido.id).url}
                        />
                    ))}
                </Grupo>
            )}

            {entregas !== null && entregas.length > 0 && (
                <Grupo
                    titulo="Órdenes que esperan repuestos"
                    icono={<ArrowUpFromLine className="size-4" />}
                    cantidad={entregas.length}
                    tono="azul"
                >
                    {entregas.map((orden) => (
                        <div
                            key={orden.id}
                            className="flex flex-col gap-2 rounded-lg border bg-background p-3"
                        >
                            <Cabecera
                                placa={orden.placa}
                                numero={orden.numero}
                                urgente={orden.urgente}
                                detalle={
                                    orden.mecanico
                                        ? `Para ${orden.mecanico}`
                                        : 'Sin mecánico'
                                }
                            />
                            <ul className="grid gap-1 text-sm">
                                {orden.lineas.map((linea, i) => (
                                    <li
                                        key={i}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span className="min-w-0 truncate">
                                            {formatearCantidad(linea.pendiente)}{' '}
                                            {linea.unidad ?? ''} ·{' '}
                                            {linea.descripcion}
                                        </span>
                                        <span
                                            className={cn(
                                                'shrink-0 text-xs font-medium',
                                                linea.hay >= linea.pendiente
                                                    ? 'text-emerald-700 dark:text-emerald-400'
                                                    : linea.hay > 0
                                                      ? 'text-amber-700 dark:text-amber-400'
                                                      : 'text-red-700 dark:text-red-400',
                                            )}
                                        >
                                            {linea.hay >= linea.pendiente
                                                ? 'Hay'
                                                : linea.hay > 0
                                                  ? `Hay ${formatearCantidad(linea.hay)}`
                                                  : 'No hay'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            {orden.se_puede ? (
                                <Button asChild className="h-11">
                                    <Link
                                        href={salidas.create.url({
                                            query: { orden: orden.id },
                                        })}
                                    >
                                        <ArrowUpFromLine className="size-4" />
                                        Entregar lo que hay
                                    </Link>
                                </Button>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    No hay stock de nada: hay que comprarlo.
                                </p>
                            )}
                        </div>
                    ))}
                </Grupo>
            )}

            {por_comprar !== null && por_comprar.length > 0 && (
                <Grupo
                    titulo="Repuestos por comprar"
                    icono={<ShoppingCart className="size-4" />}
                    cantidad={por_comprar.length}
                    tono="rojo"
                >
                    {por_comprar.map((orden) => {
                        const enTexto = orden.lineas.filter(
                            (linea) => !linea.en_catalogo,
                        );

                        return (
                            <div
                                key={orden.id}
                                className="flex flex-col gap-2 rounded-lg border bg-background p-3"
                            >
                                <Cabecera
                                    placa={orden.placa}
                                    numero={orden.numero}
                                    urgente={orden.urgente}
                                    detalle="El stock no lo cubre"
                                />
                                <ul className="grid gap-1.5 text-sm">
                                    {orden.lineas.map((linea) => (
                                        <li
                                            key={linea.repuesto_id}
                                            className="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <span className="min-w-0">
                                                {formatearCantidad(linea.falta)}{' '}
                                                {linea.unidad ?? ''} ·{' '}
                                                {linea.descripcion}
                                                {!linea.en_catalogo && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        escrito por el mecánico
                                                    </span>
                                                )}
                                            </span>
                                            {!linea.en_catalogo &&
                                                porHacer.puede_catalogar && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                pasarAlCatalogo(
                                                                    {
                                                                        orden: orden.id,
                                                                        repuesto:
                                                                            linea.repuesto_id,
                                                                    },
                                                                ).url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <PackagePlus className="size-4" />
                                                        Pasar al catálogo
                                                    </Button>
                                                )}
                                        </li>
                                    ))}
                                </ul>
                                {enTexto.length === orden.lineas.length ? (
                                    <p className="text-xs text-muted-foreground">
                                        Primero pásalos al catálogo: después se
                                        puede pedir la compra.
                                    </p>
                                ) : (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="h-11"
                                    >
                                        <Link
                                            href={compras.create.url({
                                                query: { orden: orden.id },
                                            })}
                                        >
                                            <ShoppingCart className="size-4" />
                                            Pedir la compra
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        );
                    })}
                </Grupo>
            )}

            {por_recibir !== null && por_recibir.length > 0 && (
                <Grupo
                    titulo="Compras aprobadas por recibir"
                    icono={<ArrowDownToLine className="size-4" />}
                    cantidad={por_recibir.length}
                    tono="verde"
                >
                    {por_recibir.map((pedido) => (
                        <FilaPedido
                            key={pedido.id}
                            pedido={pedido}
                            accion="Recibir"
                            href={compras.show(pedido.id).url}
                        />
                    ))}
                </Grupo>
            )}

            {porHacer.sin_comprobante !== null &&
                porHacer.sin_comprobante.length > 0 && (
                    <Grupo
                        titulo="Recepciones sin comprobante"
                        icono={<ArrowDownToLine className="size-4" />}
                        cantidad={porHacer.sin_comprobante.length}
                        tono="ambar"
                    >
                        {porHacer.sin_comprobante.map((recepcion) => (
                            <Link
                                key={recepcion.id}
                                href={ingresos.show(recepcion.id)}
                                className="flex min-h-14 items-center justify-between gap-3 rounded-lg border bg-background p-3 text-sm transition-colors hover:bg-accent/50"
                            >
                                <span className="flex min-w-0 flex-col">
                                    <span className="font-medium">
                                        {recepcion.numero}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {recepcion.proveedor}
                                    </span>
                                </span>
                                <span className="shrink-0 text-xs font-medium text-primary">
                                    Completar el comprobante
                                </span>
                            </Link>
                        ))}
                    </Grupo>
                )}
        </section>
    );
}

const TONOS = {
    ambar: 'border-amber-500/40 bg-amber-500/5',
    azul: 'border-sky-500/40 bg-sky-500/5',
    rojo: 'border-red-500/30 bg-red-500/5',
    verde: 'border-emerald-500/40 bg-emerald-500/5',
};

function Grupo({
    titulo,
    icono,
    cantidad,
    tono,
    children,
}: {
    titulo: string;
    icono: ReactNode;
    cantidad: number;
    tono: keyof typeof TONOS;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-2 rounded-xl border p-3',
                TONOS[tono],
            )}
        >
            <h2 className="flex items-center gap-2 text-sm font-semibold">
                {icono}
                {titulo}
                <span className="rounded-full bg-background px-2 py-0.5 text-xs tabular-nums">
                    {cantidad}
                </span>
            </h2>
            {children}
        </div>
    );
}

function Cabecera({
    placa,
    numero,
    urgente,
    detalle,
}: {
    placa: string;
    numero: string;
    urgente: boolean;
    detalle: string;
}) {
    return (
        <div className="flex items-center gap-3">
            <Placa placa={placa} />
            <span className="flex min-w-0 flex-col text-sm">
                <span className="flex items-center gap-2 font-medium">
                    {numero}
                    {urgente && (
                        <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">
                            Urgente
                        </span>
                    )}
                </span>
                <span className="text-xs text-muted-foreground">{detalle}</span>
            </span>
        </div>
    );
}

function FilaPedido({
    pedido,
    accion,
    href,
}: {
    pedido: Pedido;
    accion: string;
    href: string;
}) {
    return (
        <Link
            href={href}
            className="flex min-h-14 items-center justify-between gap-3 rounded-lg border bg-background p-3 text-sm transition-colors hover:bg-accent/50"
        >
            <span className="flex min-w-0 flex-col">
                <span className="flex items-center gap-2 font-medium">
                    {pedido.numero}
                    {pedido.emergencia && (
                        <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">
                            Emergencia
                        </span>
                    )}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {pedido.proveedor}
                    {pedido.total !== null &&
                        ` · ${pedido.moneda} ${pedido.total.toLocaleString('es-PE', { minimumFractionDigits: 2 })}`}
                </span>
            </span>
            <span className="shrink-0 text-xs font-medium text-primary">
                {accion}
            </span>
        </Link>
    );
}
