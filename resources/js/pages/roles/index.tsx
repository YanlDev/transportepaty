import { Head, useForm } from '@inertiajs/react';
import { Copy, Lock, Plus, ShieldCheck, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import roles, {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/RolController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { MatrizPermisos } from '@/components/roles/matriz-permisos';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { ModuloPermisos, Permiso } from '@/types';

type Rol = {
    id: number;
    name: string;
    usuarios: number;
    permisos: Permiso[];
    bloqueado: boolean;
};

type Props = {
    roles: Rol[];
    modulos: ModuloPermisos[];
};

const SIN_COPIA = 'ninguno';

function idDeLaUrl(url: string): number {
    return Number(new URL(url, window.location.origin).searchParams.get('rol'));
}

function rolDeLaUrl(lista: Rol[]): number | undefined {
    const id = idDeLaUrl(window.location.href);

    return lista.find((rol) => rol.id === id)?.id ?? lista[0]?.id;
}

export default function RolesIndex({ roles: lista, modulos }: Props) {
    const [seleccionadoId, setSeleccionadoId] = useState(() =>
        rolDeLaUrl(lista),
    );
    const [nuevoAbierto, setNuevoAbierto] = useState(false);

    const seleccionado =
        lista.find((rol) => rol.id === seleccionadoId) ?? lista[0];
    const totalPermisos = modulos.reduce(
        (suma, modulo) => suma + modulo.permisos.length,
        0,
    );

    const elegir = (id: number) => {
        setSeleccionadoId(id);
        window.history.replaceState(
            window.history.state,
            '',
            `${roles.index().url}?rol=${id}`,
        );
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Roles y permisos" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-2xl text-sm text-muted-foreground">
                    Marca qué puede hacer cada rol. Los cambios valen desde la
                    siguiente pantalla que abra cada usuario. A una persona
                    puntual se le pueden sumar permisos desde su ficha en
                    Usuarios.
                </p>

                <Button onClick={() => setNuevoAbierto(true)}>
                    <Plus className="size-4" />
                    Nuevo rol
                </Button>
            </div>

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <nav
                    aria-label="Roles"
                    className="flex gap-2 overflow-x-auto pb-1 lg:w-60 lg:shrink-0 lg:flex-col lg:overflow-visible"
                >
                    {lista.map((rol) => (
                        <button
                            key={rol.id}
                            type="button"
                            onClick={() => elegir(rol.id)}
                            aria-current={
                                rol.id === seleccionado?.id ? 'true' : undefined
                            }
                            className={cn(
                                'flex min-w-44 shrink-0 flex-col gap-1 rounded-xl border px-4 py-3 text-left transition-colors lg:min-w-0',
                                rol.id === seleccionado?.id
                                    ? 'border-marca-600/40 bg-marca-50 dark:bg-marca-950'
                                    : 'border-border bg-card hover:bg-muted/60',
                            )}
                        >
                            <span className="flex items-center gap-2 text-sm font-semibold text-foreground capitalize">
                                {rol.bloqueado ? (
                                    <Lock className="size-3.5 text-muted-foreground" />
                                ) : (
                                    <ShieldCheck className="size-3.5 text-muted-foreground" />
                                )}
                                {rol.name}
                            </span>
                            <span className="flex items-center gap-3 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1">
                                    <Users className="size-3" />
                                    {rol.usuarios}
                                </span>
                                <span className="tabular-nums">
                                    {rol.permisos.length}/{totalPermisos}{' '}
                                    permisos
                                </span>
                            </span>
                        </button>
                    ))}
                </nav>

                {seleccionado && (
                    <EditorRol
                        key={seleccionado.id}
                        rol={seleccionado}
                        modulos={modulos}
                        totalPermisos={totalPermisos}
                    />
                )}
            </div>

            <NuevoRolDialog
                open={nuevoAbierto}
                onOpenChange={setNuevoAbierto}
                roles={lista}
                onCreado={setSeleccionadoId}
            />
        </div>
    );
}

function EditorRol({
    rol,
    modulos,
    totalPermisos,
}: {
    rol: Rol;
    modulos: ModuloPermisos[];
    totalPermisos: number;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm<{
        name: string;
        permisos: Permiso[];
    }>({
        name: rol.name,
        permisos: rol.permisos,
    });

    const todos = modulos.flatMap((modulo) =>
        modulo.permisos.map((permiso) => permiso.value),
    );

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        put(update(rol.id).url, { preserveScroll: true });
    };

    return (
        <form onSubmit={guardar} className="flex min-w-0 flex-1 flex-col gap-4">
            <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 sm:flex-row sm:items-end sm:justify-between">
                <div className="w-full sm:max-w-xs">
                    <Field label="Nombre del rol" error={errors.name}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.name}
                                disabled={rol.bloqueado}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                        )}
                    </Field>
                </div>

                {rol.bloqueado ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground sm:max-w-sm">
                        <Lock className="size-4 shrink-0" />
                        El administrador siempre tiene todo. Es lo que asegura
                        que alguien pueda volver a repartir permisos.
                    </p>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="mr-1 text-sm text-muted-foreground tabular-nums">
                            {data.permisos.length} de {totalPermisos}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setData('permisos', todos)}
                        >
                            Marcar todo
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setData('permisos', [])}
                        >
                            Quitar todo
                        </Button>
                    </div>
                )}
            </section>

            {errors.permisos && (
                <p className="text-sm text-destructive">{errors.permisos}</p>
            )}

            <MatrizPermisos
                modulos={modulos}
                seleccionados={data.permisos}
                onChange={(permisos) => setData('permisos', permisos)}
                bloqueado={rol.bloqueado}
            />

            {!rol.bloqueado && (
                <div className="sticky bottom-0 z-10 -mx-4 flex items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
                    <ConfirmarBorradoDialog
                        url={destroy(rol.id).url}
                        titulo="Eliminar rol"
                        trigger={
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                disabled={rol.usuarios > 0}
                                title={
                                    rol.usuarios > 0
                                        ? 'Primero pasa sus usuarios a otro rol'
                                        : undefined
                                }
                            >
                                <Trash2 className="size-4" />
                                Eliminar rol
                            </Button>
                        }
                        descripcion={
                            <>
                                ¿Seguro que deseas eliminar el rol{' '}
                                <Resaltado>{rol.name}</Resaltado>?
                            </>
                        }
                    />

                    <div className="flex items-center gap-3">
                        {isDirty && (
                            <span className="hidden text-xs text-muted-foreground sm:inline">
                                Cambios sin guardar
                            </span>
                        )}
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing && <Spinner />}
                            Guardar permisos
                        </Button>
                    </div>
                </div>
            )}
        </form>
    );
}

function NuevoRolDialog({
    open,
    onOpenChange,
    roles: lista,
    onCreado,
}: {
    open: boolean;
    onOpenChange: (abierto: boolean) => void;
    roles: Rol[];
    onCreado: (id: number) => void;
}) {
    const { data, setData, post, processing, errors, reset, transform } =
        useForm<{
            name: string;
            copiar_de: string;
        }>({
            name: '',
            copiar_de: SIN_COPIA,
        });

    const crear = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            name: datos.name,
            copiar_de: datos.copiar_de === SIN_COPIA ? null : datos.copiar_de,
        }));

        post(store().url, {
            preserveScroll: true,
            onSuccess: (pagina) => {
                reset();
                onOpenChange(false);
                onCreado(idDeLaUrl(pagina.url));
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nuevo rol</DialogTitle>
                    <DialogDescription>
                        Ponle un nombre que diga qué hace la persona, por
                        ejemplo «despachador». Después marcas sus permisos.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={crear} className="flex flex-col gap-4">
                    <Field label="Nombre" error={errors.name} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="despachador"
                                autoFocus
                            />
                        )}
                    </Field>

                    <Field
                        label="Partir de los permisos de"
                        error={errors.copiar_de}
                        ayuda="Opcional: copia los permisos de otro rol para ajustar solo la diferencia."
                    >
                        {(id) => (
                            <Select
                                value={data.copiar_de}
                                onValueChange={(valor) =>
                                    setData('copiar_de', valor)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_COPIA}>
                                        Empezar sin permisos
                                    </SelectItem>
                                    {lista.map((rol) => (
                                        <SelectItem
                                            key={rol.id}
                                            value={String(rol.id)}
                                        >
                                            <span className="inline-flex items-center gap-2 capitalize">
                                                <Copy className="size-3.5" />
                                                {rol.name}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Crear rol
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles y permisos', href: roles.index().url }],
};
