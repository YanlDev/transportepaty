import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import sucursales, {
    store,
    update,
} from '@/actions/App/Http/Controllers/SucursalController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Sucursal } from '@/types/fleet';

type Props = {
    mode: 'create' | 'edit';
    sucursal?: Sucursal;
};

type FormData = {
    nombre: string;
    codigo: string;
    direccion: string;
    ciudad: string;
    telefono: string;
    activa: boolean;
};

export function SucursalForm({ mode, sucursal }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        nombre: sucursal?.nombre ?? '',
        codigo: sucursal?.codigo ?? '',
        direccion: sucursal?.direccion ?? '',
        ciudad: sucursal?.ciudad ?? '',
        telefono: sucursal?.telefono ?? '',
        activa: sucursal?.activa ?? true,
    });

    const activaId = useId();

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (sucursal) {
            put(update(sucursal.id).url);
        }
    };

    const volver = sucursales.index();

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Datos de la sucursal
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Información de la sede a la que se asignan vehículos y
                        conductores.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nombre" error={errors.nombre} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.nombre}
                                onChange={(e) =>
                                    setData('nombre', e.target.value)
                                }
                                placeholder="Sucursal Lima Norte"
                            />
                        )}
                    </Field>
                    <Field label="Código" error={errors.codigo} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.codigo}
                                onChange={(e) =>
                                    setData(
                                        'codigo',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="SUC-001"
                            />
                        )}
                    </Field>
                    <Field label="Ciudad" error={errors.ciudad}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.ciudad}
                                onChange={(e) =>
                                    setData('ciudad', e.target.value)
                                }
                                placeholder="Lima"
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
                                placeholder="+51 999 888 777"
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
                                    placeholder="Av. Principal 123"
                                />
                            )}
                        </Field>
                    </div>
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id={activaId}
                            checked={data.activa}
                            onCheckedChange={(value) =>
                                setData('activa', value === true)
                            }
                        />
                        <Label htmlFor={activaId} className="font-normal">
                            Sucursal activa (disponible para asignar vehículos)
                        </Label>
                    </div>
                    <InputError message={errors.activa} />
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={volver}>Cancelar</Link>
                </Button>
                <Button
                    type="submit"
                    disabled={processing}
                    className="bg-emerald-800 hover:bg-emerald-900"
                >
                    {processing && <Spinner />}
                    {mode === 'create'
                        ? 'Registrar sucursal'
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
