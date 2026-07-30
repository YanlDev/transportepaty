import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import conductores, {
    store,
    update,
} from '@/actions/App/Http/Controllers/ConductorController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { User } from '@/types/auth';
import type { Conductor } from '@/types/fleet';

// Radix Select no admite items con value vacío, así que usamos un centinela
// para representar "sin usuario asignado".
const SIN_USUARIO = '__sin_usuario__';

type Props = {
    mode: 'create' | 'edit';
    conductor?: Conductor;
    usuarios: User[];
};

type FormData = {
    user_id: string | null;
    nombres: string;
    apellidos: string;
    documento: string;
    licencia: string;
    categoria_licencia: string;
    licencia_vence: string;
    telefono: string;
    email: string;
    fecha_nacimiento: string;
    procedencia: string;
    activo: boolean;
};

export function ConductorForm({ mode, conductor, usuarios }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        user_id: conductor?.user_id ? String(conductor.user_id) : null,
        nombres: conductor?.nombres ?? '',
        apellidos: conductor?.apellidos ?? '',
        documento: conductor?.documento ?? '',
        licencia: conductor?.licencia ?? '',
        categoria_licencia: conductor?.categoria_licencia ?? '',
        licencia_vence: conductor?.licencia_vence ?? '',
        telefono: conductor?.telefono ?? '',
        email: conductor?.email ?? '',
        fecha_nacimiento: conductor?.fecha_nacimiento ?? '',
        procedencia: conductor?.procedencia ?? '',
        activo: conductor?.activo ?? true,
    });

    const activoId = useId();

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (conductor) {
            put(update(conductor.id).url);
        }
    };

    const volver = conductores.index();

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Datos del conductor
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Información personal y de licencia del conductor.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nombres" error={errors.nombres} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.nombres}
                                onChange={(e) =>
                                    setData('nombres', e.target.value)
                                }
                                placeholder="Juan Carlos"
                            />
                        )}
                    </Field>
                    <Field label="Apellidos" error={errors.apellidos} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.apellidos}
                                onChange={(e) =>
                                    setData('apellidos', e.target.value)
                                }
                                placeholder="García Pérez"
                            />
                        )}
                    </Field>
                    <Field label="Documento" error={errors.documento} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.documento}
                                onChange={(e) =>
                                    setData('documento', e.target.value)
                                }
                                placeholder="12345678"
                            />
                        )}
                    </Field>
                    <Field
                        label="Fecha de nacimiento"
                        error={errors.fecha_nacimiento}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.fecha_nacimiento}
                                onChange={(e) =>
                                    setData('fecha_nacimiento', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="Procedencia" error={errors.procedencia}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.procedencia}
                                onChange={(e) =>
                                    setData('procedencia', e.target.value)
                                }
                                placeholder="Puno"
                            />
                        )}
                    </Field>
                    <Field label="Teléfono" error={errors.telefono}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.telefono}
                                onChange={(e) =>
                                    setData('telefono', e.target.value)
                                }
                                placeholder="999888777"
                            />
                        )}
                    </Field>
                    <Field label="Email" error={errors.email}>
                        {(id) => (
                            <Input
                                id={id}
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="conductor@ejemplo.com"
                            />
                        )}
                    </Field>
                    <Field label="N° Licencia" error={errors.licencia}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.licencia}
                                onChange={(e) =>
                                    setData('licencia', e.target.value)
                                }
                                placeholder="Q12345678"
                            />
                        )}
                    </Field>
                    <Field label="Categoría" error={errors.categoria_licencia}>
                        {(id) => (
                            <Select
                                value={data.categoria_licencia}
                                onValueChange={(value) =>
                                    setData('categoria_licencia', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Seleccionar categoría" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="A-IIIa">
                                        A-IIIa
                                    </SelectItem>
                                    <SelectItem value="A-IIIb">
                                        A-IIIb
                                    </SelectItem>
                                    <SelectItem value="A-IIIc">
                                        A-IIIc
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field label="Licencia vence" error={errors.licencia_vence}>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.licencia_vence}
                                onChange={(e) =>
                                    setData('licencia_vence', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="Usuario (opcional)" error={errors.user_id}>
                        {(id) => (
                            <Select
                                value={data.user_id ?? SIN_USUARIO}
                                onValueChange={(value) =>
                                    setData(
                                        'user_id',
                                        value === SIN_USUARIO ? null : value,
                                    )
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Sin usuario" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_USUARIO}>
                                        Sin usuario
                                    </SelectItem>
                                    {usuarios.map((u) => (
                                        <SelectItem
                                            key={u.id}
                                            value={String(u.id)}
                                        >
                                            {u.name} ({u.email})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id={activoId}
                            checked={data.activo}
                            onCheckedChange={(value) =>
                                setData('activo', value === true)
                            }
                        />
                        <Label htmlFor={activoId} className="font-normal">
                            Conductor activo
                        </Label>
                    </div>
                    <InputError message={errors.activo} />
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={volver}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {mode === 'create'
                        ? 'Registrar conductor'
                        : 'Guardar cambios'}
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
