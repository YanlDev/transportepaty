import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Plus, ShoppingCart } from 'lucide-react';
import compras from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { AlmacenNav, migasAlmacen } from '@/components/almacen/almacen-nav';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { FiltroSelect } from '@/components/filtro-select';
import { Button } from '@/components/ui/button';
import { Paginacion } from '@/components/ui/paginacion';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import { TONO_SITUACION } from '@/types/compras';
import type { ValeCabecera } from '@/types/compras';
import type { EnumOption, Paginator } from '@/types/fleet';

type Props = {
    vales: Paginator<ValeCabecera>;
    filtros: { estado: string | null };
    estados: EnumOption[];
    porAprobar: number;
    puedeSolicitar: boolean;
};

/**
 * Los vales de compra: lo que se pidió, lo que espera aprobación y lo que ya
 * se puede comprar. Toda compra entra al almacén con uno aprobado.
 */
export default function ComprasIndex({
    vales,
    filtros,
    estados,
    porAprobar,
    puedeSolicitar,
}: Props) {
    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: 'Compras',
            href: compras.index().url,
        }),
    });

    const filtrar = (estado: string | null) =>
        router.get(compras.index().url, estado ? { estado } : {}, {
            preserveState: true,
            preserveScroll: true,
        });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Compras" />

            <EncabezadoPantalla
                titulo="Compras"
                descripcion="Toda compra se pide con un vale, lo aprueba gerencia y recién entonces se compra. El vale aprobado vale 5 días."
                icono={ShoppingCart}
                acciones={
                    puedeSolicitar && (
                        <Button asChild className="max-md:hidden">
                            <Link href={compras.create()}>
                                <Plus className="size-4" />
                                Pedir compra
                            </Link>
                        </Button>
                    )
                }
            />

            <AlmacenNav actual="compras" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <FiltroSelect
                    valor={filtros.estado}
                    onCambio={filtrar}
                    todos="Todos"
                    etiqueta="Estado"
                    opciones={estados}
                />
                {porAprobar > 0 && filtros.estado !== 'solicitado' && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => filtrar('solicitado')}
                    >
                        {porAprobar === 1
                            ? '1 por aprobar'
                            : `${porAprobar} por aprobar`}
                    </Button>
                )}
            </div>

            {vales.data.length === 0 ? (
                <EmptyState
                    icono={<ShoppingCart className="size-7" />}
                    titulo="No hay vales de compra"
                    descripcion="Cuando falte algo en el almacén o en una orden de trabajo, se pide desde acá."
                />
            ) : (
                <>
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>N°</TableHead>
                                    <TableHead>Pedido</TableHead>
                                    <TableHead>Proveedor</TableHead>
                                    <TableHead>Para</TableHead>
                                    <TableHead>Pidió</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {vales.data.map((vale) => (
                                    <TableRow
                                        key={vale.id}
                                        className={cn(
                                            'cursor-pointer',
                                            vale.situacion === 'anulado' &&
                                                'opacity-60',
                                        )}
                                        onClick={() =>
                                            router.visit(compras.show(vale.id))
                                        }
                                    >
                                        <TableCell className="font-medium whitespace-nowrap">
                                            {vale.numero}
                                            {vale.tipo === 'emergencia' && (
                                                <span className="ml-2 text-xs text-amber-600 dark:text-amber-400">
                                                    Emergencia
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {formatearFecha(vale.solicitado)}
                                        </TableCell>
                                        <TableCell>{vale.proveedor}</TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {vale.placa
                                                ? formatearPlaca(vale.placa)
                                                : 'Almacén'}
                                        </TableCell>
                                        <TableCell>
                                            {vale.solicitado_por ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                            {vale.simbolo}{' '}
                                            {vale.total.toLocaleString(
                                                'es-PE',
                                                { minimumFractionDigits: 2 },
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                label={vale.situacion_label}
                                                tone={
                                                    TONO_SITUACION[
                                                        vale.situacion
                                                    ]
                                                }
                                            />
                                            {vale.vence_el &&
                                                (vale.situacion === 'vigente' ||
                                                    vale.situacion ===
                                                        'parcial') && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        hasta el{' '}
                                                        {formatearFecha(
                                                            vale.vence_el,
                                                        )}
                                                    </span>
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <Paginacion paginador={vales} />
                </>
            )}

            {puedeSolicitar && (
                <AccionPrincipalMovil href={compras.create()} icono={<Plus />}>
                    Pedir compra
                </AccionPrincipalMovil>
            )}
        </div>
    );
}
