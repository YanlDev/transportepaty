import { Head, Link, router, usePage } from '@inertiajs/react';
import { Calculator, Plus, Settings2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import cotizaciones, {
    create,
    show,
} from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha } from '@/lib/format';
import type { CotizacionListItem, EnumOption, Paginator } from '@/types/fleet';

type Props = {
    cotizaciones: Paginator<CotizacionListItem>;
    filtros: { buscar: string; estado: string | null };
    estados: EnumOption[];
};

function soles(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function CotizacionesIndex({
    cotizaciones: paginador,
    filtros,
    estados,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    useEffect(() => {
        if (buscar === (filtros.buscar ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                cotizaciones.index().url,
                {
                    buscar: buscar || undefined,
                    estado: filtros.estado ?? undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros.buscar, filtros.estado]);

    const filtrarPorEstado = (estado: string | null) => {
        router.get(
            cotizaciones.index().url,
            { buscar: buscar || undefined, estado: estado ?? undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Cotizaciones" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Cotizaciones
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'tarifa armada'
                            : 'tarifas armadas'}
                    </p>
                </div>

                {puedeGestionar && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={parametrosCosto.edit()}>
                                <Settings2 className="size-4" />
                                Parámetros de costo
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nueva cotización
                            </Link>
                        </Button>
                    </div>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar por número, cliente o ruta..."
                    className="h-11 max-w-sm md:h-9"
                />
                <div className="flex flex-wrap gap-1">
                    <Button
                        variant={filtros.estado ? 'ghost' : 'secondary'}
                        size="sm"
                        onClick={() => filtrarPorEstado(null)}
                    >
                        Todas
                    </Button>
                    {estados.map((estado) => (
                        <Button
                            key={estado.value}
                            variant={
                                filtros.estado === estado.value
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="sm"
                            onClick={() => filtrarPorEstado(estado.value)}
                        >
                            {estado.label}
                        </Button>
                    ))}
                </div>
            </div>

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Calculator className="size-7" />
                    </div>
                    <p className="font-medium">
                        No hay cotizaciones que mostrar
                    </p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda
                        {puedeGestionar && ' o arma una tarifa nueva'}.
                    </p>
                </div>
            ) : (
                <>
                    <div className="flex flex-col gap-2 lg:hidden">
                        {paginador.data.map((cotizacion) => (
                            <Link
                                key={cotizacion.id}
                                href={show(cotizacion.id)}
                                className="rounded-xl border border-border bg-card p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-mono text-sm font-semibold">
                                        {cotizacion.numero}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {cotizacion.estado_label}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm">
                                    {cotizacion.cliente_nombre}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {cotizacion.origen} → {cotizacion.destino} ·{' '}
                                    {cotizacion.km} km
                                </p>
                                <p className="mt-2 text-sm font-semibold tabular-nums">
                                    S/. {soles(cotizacion.total)}
                                </p>
                            </Link>
                        ))}
                    </div>

                    <div className="hidden overflow-hidden rounded-xl border border-border lg:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>N°</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Ruta</TableHead>
                                    <TableHead className="text-right">
                                        Km
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Días
                                    </TableHead>
                                    <TableHead className="text-right">
                                        S/. por km
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((cotizacion) => (
                                    <TableRow
                                        key={cotizacion.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(show(cotizacion.id))
                                        }
                                    >
                                        <TableCell className="font-mono text-[11px] whitespace-nowrap tabular-nums">
                                            {cotizacion.numero}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatearFecha(cotizacion.fecha)}
                                        </TableCell>
                                        <TableCell className="max-w-[220px] truncate">
                                            {cotizacion.cliente_nombre}
                                        </TableCell>
                                        <TableCell className="max-w-[240px] truncate text-muted-foreground">
                                            {cotizacion.origen} →{' '}
                                            {cotizacion.destino}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {cotizacion.km}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {cotizacion.dias}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {soles(cotizacion.costo_por_km)}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {soles(cotizacion.total)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {cotizacion.estado_label}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </>
            )}
        </div>
    );
}
