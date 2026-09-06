import { Link, useForm, usePage } from '@inertiajs/react';
import { useId } from 'react';
import clientes, {
    store,
    update,
} from '@/actions/App/Http/Controllers/ClienteController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { Cliente } from '@/types/fleet';

type Props = {
    mode: 'create' | 'edit';
    cliente?: Cliente;
};

type FormData = {
    ruc: string;
    razon_social: string;
    alias: string;
    nombre_comercial: string;
    contacto: string;
    telefono: string;
    email: string;
    direccion: string;
    recurrente: boolean;
    activo: boolean;
    notas: string;
};

export function ClienteForm({ mode, cliente }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        ruc: cliente?.ruc ?? '',
        razon_social: cliente?.razon_social ?? '',
        alias: cliente?.alias ?? '',
        nombre_comercial: cliente?.nombre_comercial ?? '',
        contacto: cliente?.contacto ?? '',
        telefono: cliente?.telefono ?? '',
        email: cliente?.email ?? '',
        direccion: cliente?.direccion ?? '',
        recurrente: cliente?.recurrente ?? false,
        activo: cliente?.activo ?? true,
        notas: cliente?.notas ?? '',
    });

    const recurrenteId = useId();
    const activoId = useId();

    const { url } = usePage();
    const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (cliente) {
            put(update(cliente.id).url + query);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Datos del cliente
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        El RUC es lo que enlaza al cliente con las GR que se
                        importan.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="RUC" error={errors.ruc} required>
                        {(id) => (
                            <Input
                                id={id}
                                inputMode="numeric"
                                value={data.ruc}
                                onChange={(e) => setData('ruc', e.target.value)}
                                placeholder="20100136741"
                            />
                        )}
                    </Field>
                    <Field
                        label="Razón social"
                        error={errors.razon_social}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.razon_social}
                                onChange={(e) =>
                                    setData(
                                        'razon_social',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="MINSUR S.A."
                            />
                        )}
                    </Field>
                    <Field
                        label="Alias"
                        error={errors.alias}
                        required
                        ayuda="Nombre corto que se muestra en tablas y gráficos."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.alias}
                                onChange={(e) =>
                                    setData('alias', e.target.value)
                                }
                                placeholder="Minsur"
                            />
                        )}
                    </Field>
                    <Field
                        label="Nombre comercial"
                        error={errors.nombre_comercial}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.nombre_comercial}
                                onChange={(e) =>
                                    setData('nombre_comercial', e.target.value)
                                }
                                placeholder="Con el que se lo conoce"
                            />
                        )}
                    </Field>
                    <Field label="Contacto" error={errors.contacto}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.contacto}
                                onChange={(e) =>
                                    setData('contacto', e.target.value)
                                }
                                placeholder="Quién coordina los despachos"
                            />
                        )}
                    </Field>
                    <Field
                        label="Teléfono"
                        error={errors.telefono}
                        ayuda="Celular: habilita el botón de WhatsApp en su ficha."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="tel"
                                inputMode="tel"
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
                                placeholder="despachos@ejemplo.com"
                            />
                        )}
                    </Field>

                    <div className="sm:col-span-2">
                        <Field label="Dirección" error={errors.direccion}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.direccion}
                                    onChange={(e) =>
                                        setData('direccion', e.target.value)
                                    }
                                    placeholder="Dirección fiscal o de recojo"
                                />
                            )}
                        </Field>
                    </div>

                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id={recurrenteId}
                            checked={data.recurrente}
                            onCheckedChange={(value) =>
                                setData('recurrente', value === true)
                            }
                        />
                        <Label htmlFor={recurrenteId} className="font-normal">
                            Cliente recurrente
                        </Label>
                    </div>

                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id={activoId}
                            checked={data.activo}
                            onCheckedChange={(value) =>
                                setData('activo', value === true)
                            }
                        />
                        <Label htmlFor={activoId} className="font-normal">
                            Cliente activo
                        </Label>
                    </div>

                    <div className="sm:col-span-2">
                        <Field label="Notas" error={errors.notas}>
                            {(id) => (
                                <Textarea
                                    id={id}
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    placeholder="Condiciones acordadas, particularidades del despacho..."
                                    rows={3}
                                />
                            )}
                        </Field>
                    </div>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={clientes.index().url + query}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {mode === 'create'
                        ? 'Registrar cliente'
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
    ayuda,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    ayuda?: string;
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
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            <InputError message={error} />
        </div>
    );
}
