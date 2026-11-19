import { Link } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import compras from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PedidoPorPagar } from '@/types/compras';

/**
 * Lo que se le debe a proveedores: los pedidos aprobados con saldo, del que
 * vence primero. Cada fila lleva al pedido, donde se anota el pago.
 */
export function PorPagar({ pedidos }: { pedidos: PedidoPorPagar[] }) {
    if (pedidos.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2 rounded-xl border border-sky-500/40 bg-sky-500/5 p-3">
            <h2 className="flex items-center gap-2 text-sm font-semibold">
                <Banknote className="size-4" />
                Por pagar a proveedores
                <span className="rounded-full bg-background px-2 py-0.5 text-xs tabular-nums">
                    {pedidos.length}
                </span>
            </h2>
            <ul className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                {pedidos.map((pedido) => (
                    <li key={pedido.id}>
                        <Link
                            href={compras.show(pedido.id)}
                            className="flex min-h-14 items-center justify-between gap-3 rounded-lg border bg-background p-3 text-sm transition-colors hover:bg-accent/50"
                        >
                            <span className="flex min-w-0 flex-col">
                                <span className="font-medium">
                                    {pedido.numero} · {pedido.forma_pago}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {pedido.proveedor}
                                </span>
                            </span>
                            <span className="flex shrink-0 flex-col items-end">
                                <span className="font-semibold tabular-nums">
                                    {pedido.simbolo}{' '}
                                    {pedido.por_pagar.toLocaleString('es-PE', {
                                        minimumFractionDigits: 2,
                                    })}
                                </span>
                                {pedido.vence_el && (
                                    <span
                                        className={cn(
                                            'text-xs',
                                            pedido.vencido
                                                ? 'font-medium text-red-700 dark:text-red-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {pedido.vencido
                                            ? 'Venció el'
                                            : 'Hasta el'}{' '}
                                        {formatearFecha(pedido.vence_el)}
                                    </span>
                                )}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
