import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, User } from 'lucide-react';
import conductores, {
    create,
    edit,
    show,
} from '@/actions/App/Http/Controllers/ConductorController';
import { ConductorTarjetaMovil } from '@/components/conductores/conductor-tarjeta-movil';
import { DeleteConductorDialog } from '@/components/conductores/delete-conductor-dialog';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFiltros } from '@/hooks/use-filtros';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';
import type { ConductorListItem, Paginator } from '@/types/fleet';

type Props = {
    conductores: Paginator<ConductorListItem>;
    filtros: { buscar: string };
};

export default function ConductoresIndex({
    conductores: paginador,
    filtros,
}: Props) {
    const { url } = usePage();
    const { puedeEditar } = usePermisos();
    const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';

    const { buscar, setBuscar } = useFiltros(filtros, conductores.index().url);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Conductores" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'conductor registrado'
                            : 'conductores registrados'}
                    </p>
                </div>

                {puedeEditar && (
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo conductor
                        </Link>
                    </Button>
                )}
            </div>

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por nombres, apellidos, documento o licencia..."
                className="max-w-sm"
            />

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<User className="size-7" />}
                    titulo="No se encontraron conductores"
                    descripcion={
                        <>
                            Ajusta la búsqueda
                            {puedeEditar && ' o registra tu primer conductor'}.
                        </>
                    }
                    accion={
                        puedeEditar && (
                            <Button asChild variant="outline">
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Nuevo conductor
                                </Link>
                            </Button>
                        )
                    }
                />
            ) : (
                <>
                    <div className="flex flex-col gap-2 sm:hidden">
                        {paginador.data.map((conductor) => (
                            <ConductorTarjetaMovil
                                key={conductor.id}
                                conductor={conductor}
                                puedeEditar={puedeEditar}
                            />
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto border sm:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-12">N.°</TableHead>
                                    <TableHead>Conductor</TableHead>
                                    <TableHead>DNI</TableHead>
                                    <TableHead>Licencia</TableHead>
                                    <TableHead>Categoría</TableHead>
                                    <TableHead>Vence</TableHead>
                                    <TableHead>Celular</TableHead>
                                    <TableHead>Procedencia</TableHead>
                                    <TableHead>Estado</TableHead>
                                    {puedeEditar && (
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((conductor, indice) => (
                                    <TableRow
                                        key={conductor.id}
                                        className={cn(
                                            !conductor.activo &&
                                                'opacity-60 grayscale',
                                        )}
                                    >
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {(paginador.from ?? 1) + indice}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={show(conductor.id)}
                                                className="hover:underline"
                                            >
                                                {`${conductor.apellidos} ${conductor.nombres}`.toUpperCase()}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {conductor.documento}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {conductor.licencia ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {conductor.categoria_licencia ??
                                                '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {conductor.licencia_vence ?? '—'}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {conductor.telefono ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {conductor.procedencia ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                title={
                                                    !conductor.activo
                                                        ? [
                                                              conductor.fecha_baja,
                                                              conductor.motivo_baja,
                                                          ]
                                                              .filter(Boolean)
                                                              .join(' · ')
                                                        : undefined
                                                }
                                            >
                                                <StatusBadge
                                                    label={
                                                        conductor.activo
                                                            ? 'Activo'
                                                            : 'Inactivo'
                                                    }
                                                    tone={
                                                        conductor.activo
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                    dot={false}
                                                />
                                            </span>
                                        </TableCell>
                                        {puedeEditar && (
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950"
                                                    >
                                                        <Link
                                                            href={`${edit(conductor.id).url}${query}`}
                                                            aria-label={`Editar ${conductor.nombre_completo}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <DeleteConductorDialog
                                                        conductor={conductor}
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                                aria-label={`Eliminar ${conductor.nombre_completo}`}
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {paginador.last_page > 1 && (
                        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
                            <p className="text-sm text-muted-foreground">
                                Mostrando {paginador.from}–{paginador.to} de{' '}
                                {paginador.total}
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {paginador.links.map((link, indice) => (
                                    <Button
                                        key={indice}
                                        asChild={!!link.url}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        disabled={!link.url}
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                preserveScroll
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

ConductoresIndex.layout = {
    breadcrumbs: [{ title: 'Conductores', href: conductores.index().url }],
};
