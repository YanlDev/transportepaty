import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Car,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Trash2,
    User,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import sucursales, {
    create,
    edit,
} from '@/actions/App/Http/Controllers/SucursalController';
import { DeleteSucursalDialog } from '@/components/sucursales/delete-sucursal-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paginator, SucursalListItem } from '@/types/fleet';

type Props = {
    sucursales: Paginator<SucursalListItem>;
    filtros: { buscar: string };
};

export default function SucursalesIndex({
    sucursales: paginador,
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
                sucursales.index().url,
                { buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros.buscar]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Sucursales" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Sucursales
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'sucursal registrada'
                            : 'sucursales registradas'}
                    </p>
                </div>

                {puedeGestionar && (
                    <Button
                        asChild
                        className="bg-emerald-800 hover:bg-emerald-900"
                    >
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nueva sucursal
                        </Link>
                    </Button>
                )}
            </div>

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por nombre, código o ciudad..."
                className="max-w-sm"
            />

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Building2 className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron sucursales</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda
                        {puedeGestionar && ' o registra tu primera sucursal'}.
                    </p>
                    {puedeGestionar && (
                        <Button asChild variant="outline" className="mt-6">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nueva sucursal
                            </Link>
                        </Button>
                    )}
                </div>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {paginador.data.map((sucursal) => (
                            <SucursalCard
                                key={sucursal.id}
                                sucursal={sucursal}
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

function SucursalCard({
    sucursal,
    puedeGestionar,
}: {
    sucursal: SucursalListItem;
    puedeGestionar: boolean;
}) {
    return (
        <article className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-800">
                            <Building2 className="size-4.5" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-foreground">
                                {sucursal.nombre}
                            </h2>
                            <p className="font-mono text-xs text-muted-foreground">
                                {sucursal.codigo}
                            </p>
                        </div>
                    </div>
                </div>
                <span
                    className={
                        sucursal.activa
                            ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/20'
                            : 'inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                    }
                >
                    {sucursal.activa ? 'Activa' : 'Inactiva'}
                </span>
            </div>

            <dl className="space-y-1.5 text-sm text-muted-foreground">
                <div className="flex items-center gap-2">
                    <MapPin className="size-3.5 shrink-0" />
                    {[sucursal.direccion, sucursal.ciudad]
                        .filter(Boolean)
                        .join(', ') || '—'}
                </div>
                <div className="flex items-center gap-2">
                    <Phone className="size-3.5 shrink-0" />
                    {sucursal.telefono ?? '—'}
                </div>
            </dl>

            <div className="flex items-center gap-4 border-t border-border pt-3 text-sm">
                <span className="flex items-center gap-1.5 text-foreground">
                    <Car className="size-4 text-muted-foreground" />
                    {sucursal.vehiculos_count} vehículo(s)
                </span>
                <span className="flex items-center gap-1.5 text-foreground">
                    <User className="size-4 text-muted-foreground" />
                    {sucursal.conductores_count} conductor(es)
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
                        <Link href={edit(sucursal.id)}>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </Button>
                    <DeleteSucursalDialog
                        sucursal={sucursal}
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

SucursalesIndex.layout = {
    breadcrumbs: [{ title: 'Sucursales', href: sucursales.index().url }],
};
