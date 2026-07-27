import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Truck } from 'lucide-react';
import vehiculos, {
    create,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { VehiculoFiltros } from '@/components/vehiculos/vehiculo-filtros';
import type { FiltrosVehiculo } from '@/hooks/use-vehiculo-filtros';
import type { EnumOption, Paginator, VehiculoListItem } from '@/types/fleet';

type Props = {
    vehiculos: Paginator<VehiculoListItem>;
    filtros: FiltrosVehiculo;
    estados: EnumOption[];
    tipos: EnumOption[];
    cajas: EnumOption[];
};

export default function VehiculosIndex({
    vehiculos: paginador,
    filtros,
    estados,
    tipos,
    cajas,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Vehículos" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Vehículos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'vehículo registrado'
                            : 'vehículos registrados'}
                    </p>
                </div>

                {puedeGestionar && (
                    <Button asChild className="bg-navy-800 hover:bg-navy-900">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo vehículo
                        </Link>
                    </Button>
                )}
            </div>

            <VehiculoFiltros
                filtros={filtros}
                tipos={tipos}
                estados={estados}
                cajas={cajas}
            />

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Truck className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron vehículos</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta los filtros de búsqueda
                        {puedeGestionar && ' o registra tu primer vehículo'}.
                    </p>
                    {puedeGestionar && (
                        <Button asChild variant="outline" className="mt-6">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nuevo vehículo
                            </Link>
                        </Button>
                    )}
                </div>
            ) : (
                <>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Placa</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Marca</TableHead>
                                    <TableHead>Modelo</TableHead>
                                    <TableHead className="text-right">
                                        Año
                                    </TableHead>
                                    <TableHead>Caja</TableHead>
                                    <TableHead>Color</TableHead>
                                    <TableHead className="text-right">
                                        Ejes
                                    </TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((vehiculo) => (
                                    <TableRow key={vehiculo.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={show(vehiculo.id)}
                                                className="hover:underline"
                                            >
                                                {vehiculo.placa}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {vehiculo.tipo_label}
                                        </TableCell>
                                        <TableCell>
                                            {vehiculo.marca ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {vehiculo.modelo ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {vehiculo.anio ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {vehiculo.caja_label ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {vehiculo.color ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {vehiculo.ejes ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <EstadoBadge
                                                estado={vehiculo.estado}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}

function Paginacion({ paginador }: { paginador: Paginator<VehiculoListItem> }) {
    if (paginador.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
            <p className="text-sm text-muted-foreground">
                Mostrando {paginador.from}–{paginador.to} de {paginador.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {paginador.links.map((link, indice) => (
                    <Button
                        key={indice}
                        asChild={!!link.url}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                        className={
                            link.active ? 'bg-navy-800 hover:bg-navy-900' : ''
                        }
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}

VehiculosIndex.layout = {
    breadcrumbs: [{ title: 'Vehículos', href: vehiculos.index().url }],
};
