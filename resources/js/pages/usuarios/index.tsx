import { Head, Link, usePage } from '@inertiajs/react';
import {
    AtSign,
    Mail,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-react';
import usuarios, {
    create,
    edit,
} from '@/actions/App/Http/Controllers/UserController';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DeleteUserDialog } from '@/components/usuarios/delete-user-dialog';
import { useFiltros } from '@/hooks/use-filtros';
import { cn } from '@/lib/utils';
import type { Paginator, UserListItem } from '@/types/fleet';

type Props = {
    usuarios: Paginator<UserListItem>;
    filtros: { buscar: string };
};

/**
 * Cada rol con su color. No usa `StatusBadge` porque ahí los tonos significan
 * estado —bien, ojo, mal— y acá solo distinguen a quién es quién.
 *
 * El contador es verde y no celeste: desde que el acento de la app es el azul
 * del logo, el celeste quedaba a un paso del badge del admin y los dos se
 * leían como el mismo rol de reojo.
 */
const ROLE_BADGES: Record<string, { label: string; className: string }> = {
    admin: {
        label: 'Administrador',
        className:
            'bg-marca-50 text-marca-700 ring-1 ring-marca-600/20 dark:bg-marca-950 dark:text-marca-300',
    },
    contador: {
        label: 'Contador',
        className:
            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-950 dark:text-emerald-300',
    },
    visor: {
        label: 'Visor',
        className:
            'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300',
    },
};

export default function UsuariosIndex({ usuarios: paginador, filtros }: Props) {
    const { auth } = usePage().props;

    const { buscar, setBuscar } = useFiltros(filtros, usuarios.index().url);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Usuarios" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
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
                placeholder="Buscar por nombre, usuario o correo..."
                className="max-w-sm"
            />

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<Users className="size-7" />}
                    titulo="No se encontraron usuarios"
                    descripcion="Ajusta la búsqueda o crea tu primer usuario."
                    accion={
                        <Button asChild variant="outline">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nuevo usuario
                            </Link>
                        </Button>
                    }
                />
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
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-marca-50 text-marca-800 dark:bg-marca-950 dark:text-marca-300">
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
                            <AtSign className="size-3" />
                            {usuario.username}
                        </p>
                        {usuario.email && (
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Mail className="size-3" />
                                {usuario.email}
                            </p>
                        )}
                    </div>
                </div>
                {badge && (
                    <span
                        className={cn(
                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            badge.className,
                        )}
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
