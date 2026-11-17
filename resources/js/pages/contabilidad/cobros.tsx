import { Head, Link, router } from '@inertiajs/react';
import { Ban, HandCoins, Plus, Split } from 'lucide-react';
import cobrosRutas from '@/actions/App/Http/Controllers/CobroController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import { AnularCobroDialog } from '@/components/contabilidad/anular-cobro-dialog';
import { RegistrarCobroDialog } from '@/components/contabilidad/registrar-cobro-dialog';
import { EmptyState } from '@/components/empty-state';
import { ListadoResponsivo } from '@/components/listado-responsivo';
import { Button } from '@/components/ui/button';
import { Paginacion } from '@/components/ui/paginacion';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ClienteDeCobro,
    Cobro,
    CuentaDeCobro,
    FacturaPorCobrar,
} from '@/types/contabilidad';
import type { Paginator } from '@/types/fleet';

type Props = {
    cobros: Paginator<Cobro>;
    filtros: { con_saldo: boolean };
    saldoAFavor: { moneda: string; simbolo: string; monto: number }[];
    cuentasDeCobro: CuentaDeCobro[];
    clientesDeCobro: ClienteDeCobro[];
    facturasPorCobrar: FacturaPorCobrar[];
};

function dinero(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** Qué facturas pagó, con el monto cuando no fue una sola. */
function Facturas({ cobro }: { cobro: Cobro }) {
    if (cobro.facturas.length === 0) {
        return (
            <span className="text-muted-foreground italic">
                Adelanto: todavía no paga ninguna factura
            </span>
        );
    }

    return (
        <span className="flex flex-wrap gap-x-2 gap-y-0.5">
            {cobro.facturas.map((factura) => (
                <span key={factura.id} className="whitespace-nowrap">
                    <span className="font-mono">{factura.numero}</span>
                    {cobro.facturas.length > 1 && (
                        <span className="text-muted-foreground tabular-nums">
                            {' '}
                            {dinero(factura.monto)}
                        </span>
                    )}
                </span>
            ))}
        </span>
    );
}

function Acciones({
    cobro,
    catalogos,
}: {
    cobro: Cobro;
    catalogos: Pick<
        Props,
        'cuentasDeCobro' | 'clientesDeCobro' | 'facturasPorCobrar'
    >;
}) {
    return (
        <div className="flex items-center justify-end gap-1">
            {cobro.aplicable && (
                <RegistrarCobroDialog
                    cuentas={catalogos.cuentasDeCobro}
                    clientes={catalogos.clientesDeCobro}
                    facturas={catalogos.facturasPorCobrar}
                    cobro={cobro}
                    trigger={
                        <Button variant="outline" size="sm">
                            <Split className="size-4" />
                            Aplicar saldo
                        </Button>
                    }
                />
            )}
            {cobro.anulable && (
                <AnularCobroDialog
                    cobro={cobro}
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-destructive"
                            aria-label="Anular el cobro"
                            title="Anular el cobro"
                        >
                            <Ban className="size-4" />
                        </Button>
                    }
                />
            )}
        </div>
    );
}

export default function Cobros({
    cobros,
    filtros,
    saldoAFavor,
    cuentasDeCobro,
    clientesDeCobro,
    facturasPorCobrar,
}: Props) {
    const { puede } = usePermisos();
    const catalogos = { cuentasDeCobro, clientesDeCobro, facturasPorCobrar };

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <Head title="Cobros" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-lg font-semibold">Cobros</h1>
                    <p className="text-sm text-muted-foreground">
                        Cada depósito que entró y las facturas que pagó.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={contabilidad.index()}>Ver cobranza</Link>
                    </Button>
                    {puede('cobranza.gestionar') && (
                        <RegistrarCobroDialog
                            cuentas={cuentasDeCobro}
                            clientes={clientesDeCobro}
                            facturas={facturasPorCobrar}
                            trigger={
                                <Button>
                                    <Plus className="size-4" />
                                    Registrar cobro
                                </Button>
                            }
                        />
                    )}
                </div>
            </div>

            {/* Lo que entró y todavía no paga ninguna factura. */}
            {saldoAFavor.length > 0 && (
                <div className="flex flex-wrap items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                    <span className="font-medium text-amber-900 dark:text-amber-200">
                        Saldo a favor de clientes:{' '}
                        {saldoAFavor
                            .map(
                                (saldo) =>
                                    `${saldo.simbolo} ${dinero(saldo.monto)}`,
                            )
                            .join(' · ')}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.get(
                                cobrosRutas.index().url,
                                filtros.con_saldo ? {} : { con_saldo: 1 },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {filtros.con_saldo
                            ? 'Ver todos los cobros'
                            : 'Ver solo los que tienen saldo'}
                    </Button>
                </div>
            )}

            <ListadoResponsivo
                items={cobros.data}
                clave={(cobro) => cobro.id}
                vacio={
                    <EmptyState
                        expandir={false}
                        icono={<HandCoins className="size-7" />}
                        titulo={
                            filtros.con_saldo
                                ? 'Ningún cobro tiene saldo a favor'
                                : 'Todavía no hay cobros'
                        }
                        descripcion="Registra cada depósito y marca las facturas que paga."
                    />
                }
                tarjeta={(cobro) => (
                    <div
                        className={cn(
                            'flex flex-col gap-2 rounded-xl border bg-card p-3 text-sm',
                            cobro.anulado && 'opacity-60',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span
                                className={cn(
                                    'text-base font-semibold tabular-nums',
                                    cobro.anulado && 'line-through',
                                )}
                            >
                                {cobro.simbolo} {dinero(cobro.monto)}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {formatearFecha(cobro.fecha)}
                            </span>
                        </div>
                        <p className="text-muted-foreground">
                            {cobro.cuenta ?? 'Sin cuenta'}
                            {cobro.numero_operacion &&
                                ` · op. ${cobro.numero_operacion}`}
                            {cobro.cliente && ` · ${cobro.cliente}`}
                        </p>
                        <Facturas cobro={cobro} />
                        {cobro.saldo_a_favor > 0 && (
                            <p className="font-medium text-amber-700 dark:text-amber-500">
                                A favor: {cobro.simbolo}{' '}
                                {dinero(cobro.saldo_a_favor)}
                            </p>
                        )}
                        {cobro.anulado && (
                            <p className="text-xs">
                                Anulado: {cobro.motivo_anulacion}
                            </p>
                        )}
                        <Acciones cobro={cobro} catalogos={catalogos} />
                    </div>
                )}
                encabezado={
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Fecha</TableHead>
                        <TableHead>Cuenta</TableHead>
                        <TableHead>N° operación</TableHead>
                        <TableHead>Cliente</TableHead>
                        <TableHead className="text-right">Monto</TableHead>
                        <TableHead>Facturas que pagó</TableHead>
                        <TableHead className="text-right">A favor</TableHead>
                        <TableHead />
                    </TableRow>
                }
                fila={(cobro) => (
                    <TableRow className={cn(cobro.anulado && 'opacity-60')}>
                        <TableCell className="whitespace-nowrap tabular-nums">
                            {formatearFecha(cobro.fecha)}
                        </TableCell>
                        <TableCell className="whitespace-nowrap">
                            {cobro.cuenta ?? (
                                <span className="text-amber-700 dark:text-amber-500">
                                    falta la cuenta
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="font-mono text-xs whitespace-nowrap">
                            {cobro.numero_operacion ?? (
                                <span className="font-sans text-amber-700 dark:text-amber-500">
                                    sin operación
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="max-w-48 truncate">
                            {cobro.cliente ?? '—'}
                        </TableCell>
                        <TableCell
                            className={cn(
                                'text-right font-medium whitespace-nowrap tabular-nums',
                                cobro.anulado && 'line-through',
                            )}
                        >
                            {cobro.simbolo} {dinero(cobro.monto)}
                        </TableCell>
                        <TableCell className="text-xs">
                            <Facturas cobro={cobro} />
                            {cobro.anulado && (
                                <span className="block">
                                    Anulado
                                    {cobro.anulado_el &&
                                        ` el ${formatearFecha(cobro.anulado_el)}`}
                                    {cobro.anulado_por &&
                                        ` por ${cobro.anulado_por}`}
                                    : {cobro.motivo_anulacion}
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                            {cobro.saldo_a_favor > 0 ? (
                                <span className="font-medium text-amber-700 dark:text-amber-500">
                                    {dinero(cobro.saldo_a_favor)}
                                </span>
                            ) : (
                                '—'
                            )}
                        </TableCell>
                        <TableCell>
                            <Acciones cobro={cobro} catalogos={catalogos} />
                        </TableCell>
                    </TableRow>
                )}
            />

            {cobros.data.length > 0 && <Paginacion paginador={cobros} />}
        </div>
    );
}

Cobros.layout = {
    breadcrumbs: [
        { title: 'Contabilidad', href: contabilidad.index().url },
        { title: 'Cobros', href: cobrosRutas.index().url },
    ],
};
