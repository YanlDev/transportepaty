import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import conductores, {
    create,
    edit,
} from '@/actions/App/Http/Controllers/ConductorController';
import { DeleteConductorDialog } from '@/components/conductores/delete-conductor-dialog';
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
import type { ConductorListItem, Paginator } from '@/types/fleet';

type Props = {
    conductores: Paginator<ConductorListItem>;
    filtros: { buscar: string };
};

export default function ConductoresIndex({
    conductores: paginador,
    filtros,
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
                conductores.index().url,
                { buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros.buscar]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Conductores" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Conductores
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'conductor registrado'
                            : 'conductores registrados'}
                    </p>
                </div>

                {puedeGestionar && (
                    <Button asChild className="bg-navy-800 hover:bg-navy-900">
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
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <User className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron conductores</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda
                        {puedeGestionar && ' o registra tu primer conductor'}.
                    </p>
                    {puedeGestionar && (
                        <Button asChild variant="outline" className="mt-6">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nuevo conductor
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
                                    <TableHead>Conductor</TableHead>
                                    <TableHead>DNI</TableHead>
                                    <TableHead>Licencia</TableHead>
                                    <TableHead>Categoría</TableHead>
                                    <TableHead>Vence</TableHead>
                                    <TableHead>Celular</TableHead>
                                    <TableHead>Procedencia</TableHead>
                                    <TableHead>Estado</TableHead>
                                    {puedeGestionar && (
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((conductor) => (
                                    <TableRow key={conductor.id}>
                                        <TableCell className="font-medium">
                                            {conductor.nombre_completo}
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
                                                className={
                                                    conductor.activo
                                                        ? 'inline-flex items-center rounded-full bg-navy-50 px-2 py-0.5 text-[11px] font-medium text-navy-700 ring-1 ring-navy-600/20'
                                                        : 'inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                                                }
                                            >
                                                {conductor.activo
                                                    ? 'Activo'
                                                    : 'Inactivo'}
                                            </span>
                                        </TableCell>
                                        {puedeGestionar && (
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={edit(
                                                                conductor.id,
                                                            )}
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
                                        className={
                                            link.active
                                                ? 'bg-navy-800 hover:bg-navy-900'
                                                : ''
                                        }
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
