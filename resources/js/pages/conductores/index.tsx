import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Car,
    Pencil,
    Phone,
    Plus,
    Trash2,
    User,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import conductores, {
    create,
    edit,
} from '@/actions/App/Http/Controllers/ConductorController';
import { DeleteConductorDialog } from '@/components/conductores/delete-conductor-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
                    <Button
                        asChild
                        className="bg-emerald-800 hover:bg-emerald-900"
                    >
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
                placeholder="Buscar por nombres, apellidos o documento..."
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
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {paginador.data.map((conductor) => (
                            <ConductorCard
                                key={conductor.id}
                                conductor={conductor}
                                puedeGestionar={puedeGestionar}
                            />
                        ))}
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
                                                ? 'bg-emerald-800 hover:bg-emerald-900'
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

function ConductorCard({
    conductor,
    puedeGestionar,
}: {
    conductor: ConductorListItem;
    puedeGestionar: boolean;
}) {
    return (
        <article className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-800">
                            <User className="size-4.5" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-foreground">
                                {conductor.nombre_completo}
                            </h2>
                            <p className="font-mono text-xs text-muted-foreground">
                                {conductor.documento}
                            </p>
                        </div>
                    </div>
                </div>
                <span
                    className={
                        conductor.activo
                            ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/20'
                            : 'inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                    }
                >
                    {conductor.activo ? 'Activo' : 'Inactivo'}
                </span>
            </div>

            <dl className="space-y-1.5 text-sm text-muted-foreground">
                {conductor.sucursal && (
                    <div className="flex items-center gap-2">
                        <User className="size-3.5 shrink-0" />
                        {conductor.sucursal.nombre}
                    </div>
                )}
                <div className="flex items-center gap-2">
                    <Phone className="size-3.5 shrink-0" />
                    {conductor.telefono ?? '—'}
                </div>
                {conductor.email && (
                    <div className="flex items-center gap-2 text-xs">
                        {conductor.email}
                    </div>
                )}
            </dl>

            <div className="flex items-center gap-4 border-t border-border pt-3 text-sm">
                <span className="flex items-center gap-1.5 text-foreground">
                    <Car className="size-4 text-muted-foreground" />
                    {conductor.vehiculos_count} vehículo(s)
                </span>
            </div>

            {puedeGestionar && (
                <div className="mt-auto flex items-center gap-2">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="flex-1"
                    >
                        <Link href={edit(conductor.id)}>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </Button>
                    <DeleteConductorDialog
                        conductor={conductor}
                        trigger={
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        }
                    />
                </div>
            )}
        </article>
    );
}

ConductoresIndex.layout = {
    breadcrumbs: [{ title: 'Conductores', href: conductores.index().url }],
};
