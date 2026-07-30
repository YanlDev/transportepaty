import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import usuarios, {
    store,
    update,
} from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { ConductorLinkOption } from '@/types/fleet';

// Radix Select no admite items con value vacío, así que usamos un centinela
// para representar "sin conductor vinculado".
const SIN_CONDUCTOR = '__sin_conductor__';

const ROLE_LABELS: Record<string, string> = {
    admin: 'Administrador',
    conductor: 'Conductor',
    visor: 'Visor',
};

type UsuarioEdit = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    conductor_id: number | null;
};

type Props = {
    mode: 'create' | 'edit';
    usuario?: UsuarioEdit;
    roles: string[];
    conductores: ConductorLinkOption[];
};

type FormData = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    conductor_id: string | null;
};

export function UserForm({ mode, usuario, roles, conductores }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        password: '',
        password_confirmation: '',
        role: usuario?.role ?? '',
        conductor_id: usuario?.conductor_id
            ? String(usuario.conductor_id)
            : null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (usuario) {
            put(update(usuario.id).url);
        }
    };

    const cambiarRol = (value: string) => {
        setData('role', value);

        if (value !== 'conductor') {
            setData('conductor_id', null);
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
                    <Field label="Email" error={errors.email} required>
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
                    <Field label="Rol" error={errors.role} required>
                        {(id) => (
                            <Select
                                value={data.role}
                                onValueChange={cambiarRol}
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Seleccionar rol" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((rol) => (
                                        <SelectItem key={rol} value={rol}>
                                            {ROLE_LABELS[rol] ?? rol}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    {data.role === 'conductor' && (
                        <Field
                            label="Conductor vinculado"
                            error={errors.conductor_id}
                        >
                            {(id) => (
                                <Select
                                    value={data.conductor_id ?? SIN_CONDUCTOR}
                                    onValueChange={(value) =>
                                        setData(
                                            'conductor_id',
                                            value === SIN_CONDUCTOR
                                                ? null
                                                : value,
                                        )
                                    }
                                >
                                    <SelectTrigger id={id}>
                                        <SelectValue placeholder="Sin conductor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={SIN_CONDUCTOR}>
                                            Sin conductor
                                        </SelectItem>
                                        {conductores.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.nombre_completo}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    )}

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

function Field({
    label,
    error,
    required,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    children: (id: string) => React.ReactNode;
}) {
    const id = useId();

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>
                {label}
                {required && <span className="text-destructive"> *</span>}
            </Label>
            {children(id)}
            <InputError message={error} />
        </div>
    );
}
