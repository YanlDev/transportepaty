import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import usuarios, {
    store,
    update,
} from '@/actions/App/Http/Controllers/UserController';
import { MatrizPermisos } from '@/components/roles/matriz-permisos';
import { Button } from '@/components/ui/button';
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
import type { ModuloPermisos, Permiso, RolConPermisos } from '@/types';

const ROLE_LABELS: Record<string, string> = {
    admin: 'Administrador',
    visor: 'Visor',
    contador: 'Contador',
};

/**
 * Qué habilita el rol, en una línea. Se muestra al elegirlo porque el nombre
 * del rol no dice lo suficiente para decidir cuál darle a alguien. Los roles
 * nuevos no tienen descripción escrita: se resume cuántos permisos llevan.
 */
function describirRol(rol: RolConPermisos | undefined, total: number) {
    if (!rol) {
        return undefined;
    }

    if (rol.name === 'admin') {
        return 'Acceso completo, incluidas las cuentas de los demás y los roles.';
    }

    return `${rol.permisos.length} de ${total} permisos. Se ajustan en Roles y permisos.`;
}

type UsuarioEdit = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: string | null;
    permisos: Permiso[];
};

type Props = {
    mode: 'create' | 'edit';
    usuario?: UsuarioEdit;
    roles: RolConPermisos[];
    modulos: ModuloPermisos[];
};

type FormData = {
    name: string;
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    permisos: Permiso[];
};

export function UserForm({ mode, usuario, roles, modulos }: Props) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<FormData>({
            name: usuario?.name ?? '',
            username: usuario?.username ?? '',
            email: usuario?.email ?? '',
            password: '',
            password_confirmation: '',
            role: usuario?.role ?? '',
            permisos: usuario?.permisos ?? [],
        });
    const [verExtras, setVerExtras] = useState(
        (usuario?.permisos.length ?? 0) > 0,
    );

    const rolElegido = roles.find((rol) => rol.name === data.role);
    const totalPermisos = modulos.reduce(
        (suma, modulo) => suma + modulo.permisos.length,
        0,
    );
    // Lo que ya da el rol no se cuenta como extra: se ve marcado y fijo.
    const delRol = rolElegido?.permisos ?? [];
    const extras = data.permisos.filter((p) => !delRol.includes(p));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Al admin no le hacen falta sueltos: ya tiene todo. Y lo que ya da el
        // rol elegido tampoco se guarda aparte, o sobreviviría a un cambio
        // de rol sin que nadie lo viera.
        transform((datos) => ({
            ...datos,
            permisos: datos.role === 'admin' ? [] : extras,
        }));

        if (mode === 'create') {
            post(store().url);
        } else if (usuario) {
            put(update(usuario.id).url);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Datos de la cuenta
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Información de acceso y rol del usuario.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nombre" error={errors.name} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Juan Pérez"
                            />
                        )}
                    </Field>
                    <Field
                        label="Usuario"
                        error={errors.username}
                        ayuda="Con esto entra al sistema. Letras, números, guiones y guiones bajos."
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.username}
                                onChange={(e) =>
                                    setData('username', e.target.value)
                                }
                                autoCapitalize="none"
                                spellCheck={false}
                                placeholder="jperez"
                            />
                        )}
                    </Field>
                    <Field
                        label="Correo"
                        error={errors.email}
                        ayuda="Opcional, solo como dato de contacto. Al sistema se entra con el usuario."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="usuario@ejemplo.com"
                            />
                        )}
                    </Field>
                    <Field
                        label="Rol"
                        error={errors.role}
                        ayuda={describirRol(rolElegido, totalPermisos)}
                        required
                    >
                        {(id) => (
                            <Select
                                value={data.role}
                                onValueChange={(value) =>
                                    setData('role', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Seleccionar rol" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((rol) => (
                                        <SelectItem
                                            key={rol.name}
                                            value={rol.name}
                                        >
                                            {ROLE_LABELS[rol.name] ?? rol.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    {mode === 'create' && (
                        <>
                            <Field
                                label="Contraseña"
                                error={errors.password}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="password"
                                        autoComplete="new-password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="••••••••"
                                    />
                                )}
                            </Field>
                            <Field
                                label="Confirmar contraseña"
                                error={errors.password_confirmation}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="password"
                                        autoComplete="new-password"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="••••••••"
                                    />
                                )}
                            </Field>
                        </>
                    )}
                </div>
            </section>

            {data.role !== 'admin' && (
                <section className="rounded-xl border border-border bg-card p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-sm font-semibold text-foreground">
                                Permisos adicionales
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Además de los de su rol, solo para esta persona.{' '}
                                {extras.length > 0
                                    ? `Tiene ${extras.length} extra.`
                                    : 'No tiene ninguno.'}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setVerExtras((visible) => !visible)}
                        >
                            {verExtras ? 'Ocultar' : 'Agregar permisos'}
                        </Button>
                    </div>

                    {verExtras && (
                        <div className="mt-4">
                            {errors.permisos && (
                                <p className="mb-3 text-sm text-destructive">
                                    {errors.permisos}
                                </p>
                            )}
                            <MatrizPermisos
                                modulos={modulos}
                                seleccionados={extras}
                                heredados={delRol}
                                className="xl:grid-cols-2"
                                onChange={(permisos) =>
                                    setData('permisos', permisos)
                                }
                            />
                        </div>
                    )}
                </section>
            )}

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={usuarios.index()}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {mode === 'create' ? 'Crear usuario' : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}
