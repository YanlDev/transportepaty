import { Head, Link, router, usePage } from '@inertiajs/react';
import { Mail, Pencil, Plus, ShieldCheck, Trash2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import usuarios, {
    create,
    edit,
} from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DeleteUserDialog } from '@/components/usuarios/delete-user-dialog';
import type { Paginator, UserListItem } from '@/types/fleet';

type Props = {
    usuarios: Paginator<UserListItem>;
    filtros: { buscar: string };
};

const ROLE_BADGES: Record<string, { label: string; className: string }> = {
    admin: {
        label: 'Administrador',
        className: 'bg-navy-50 text-navy-700 ring-1 ring-navy-600/20',
    },
    conductor: {
        label: 'Conductor',
        className: 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20',
    },
    visor: {
        label: 'Visor',
        className: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
    },
};

export default function UsuariosIndex({ usuarios: paginador, filtros }: Props) {
    const { auth } = usePage().props;

    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    useEffect(() => {
        if (buscar === (filtros.buscar ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                usuarios.index().url,
                { buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros.buscar]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Usuarios" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Usuarios
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'cuenta registrada'
                            : 'cuentas registradas'}
                    </p>
                </div>

                <Button asChild>
                    <Link href={create()}>
                        <Plus className="size-4" />
                        Nuevo usuario
                    </Link>
                </Button>
            </div>

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por nombre o email..."
                className="max-w-sm"
            />

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-none bg-muted text-muted-foreground">
                        <Users className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron usuarios</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda o crea tu primer usuario.
                    </p>
                    <Button asChild variant="outline" className="mt-6">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo usuario
                        </Link>
                    </Button>
                </div>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {paginador.data.map((usuario) => (
                            <UsuarioCard
                                key={usuario.id}
                                usuario={usuario}
                                esActual={usuario.id === auth.user.id}
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

function UsuarioCard({
    usuario,
    esActual,
}: {
    usuario: UserListItem;
    esActual: boolean;
}) {
    const rol = usuario.roles[0]?.name;
    const badge = rol ? ROLE_BADGES[rol] : undefined;

    return (
        <article className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-navy-50 text-navy-800">
                        <ShieldCheck className="size-4.5" />
                    </span>
                    <div>
                        <h2 className="text-sm font-semibold text-foreground">
                            {usuario.name}
                            {esActual && (
                                <span className="ml-1 text-xs font-normal text-muted-foreground">
                                    (tú)
                                </span>
                            )}
                        </h2>
                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Mail className="size-3" />
                            {usuario.email}
                        </p>
                    </div>
                </div>
                {badge && (
                    <span
                        className={`inline-flex items-center rounded-none px-2 py-0.5 text-[11px] font-medium ${badge.className}`}
                    >
                        {badge.label}
                    </span>
                )}
            </div>

            <div className="mt-auto flex items-center gap-2 border-t border-border pt-3">
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="flex-1 border-amber-300 text-amber-700 hover:bg-amber-50 hover:text-amber-800 dark:border-amber-800 dark:text-amber-500 dark:hover:bg-amber-950"
                >
                    <Link href={edit(usuario.id)}>
                        <Pencil className="size-4" />
                        Editar
                    </Link>
                </Button>
                {!esActual && (
                    <DeleteUserDialog
                        usuario={usuario}
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
                )}
            </div>
        </article>
    );
}

UsuariosIndex.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: usuarios.index().url }],
};
