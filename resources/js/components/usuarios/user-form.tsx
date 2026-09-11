import { Link, useForm } from '@inertiajs/react';
import usuarios, {
    store,
    update,
} from '@/actions/App/Http/Controllers/UserController';
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

const ROLE_LABELS: Record<string, string> = {
    admin: 'Administrador',
    visor: 'Visor',
    contador: 'Contador',
};

/**
 * Qué habilita cada rol, en una línea. Se muestra al elegirlo porque el nombre
 * del rol no dice lo suficiente para decidir cuál darle a alguien.
 */
const ROLE_DESCRIPCIONES: Record<string, string> = {
    admin: 'Acceso completo: gestiona flota, conductores, viajes, cobranza, asistencia y las cuentas de los demás.',
    visor: 'Solo lectura de la operación: flota, conductores, viajes, clientes y cotizaciones. No ve cobranza.',
    contador: 'Solo la cobranza: facturas, pagos y cuentas. Lee viajes y unidades para facturar contra ellos.',
};

type UsuarioEdit = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: string | null;
};

type Props = {
    mode: 'create' | 'edit';
    usuario?: UsuarioEdit;
    roles: string[];
};

type FormData = {
    name: string;
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
};

export function UserForm({ mode, usuario, roles }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        name: usuario?.name ?? '',
        username: usuario?.username ?? '',
        email: usuario?.email ?? '',
        password: '',
        password_confirmation: '',
        role: usuario?.role ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

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
                        ayuda={ROLE_DESCRIPCIONES[data.role]}
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
                                        <SelectItem key={rol} value={rol}>
                                            {ROLE_LABELS[rol] ?? rol}
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
