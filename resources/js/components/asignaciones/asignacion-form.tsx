import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import asignaciones, {
    store,
    update,
} from '@/actions/App/Http/Controllers/AsignacionController';
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
import { Textarea } from '@/components/ui/textarea';
import { formatearPlaca } from '@/lib/format';
import type {
    Asignacion,
    ConductorOption,
    VehiculoOption,
} from '@/types/fleet';

// Radix Select no admite items con value vacío, así que usamos un centinela
// para representar "sin carreta enganchada".
const SIN_CARRETA = '__sin_carreta__';

type Props = {
    mode: 'create' | 'edit';
    asignacion?: Asignacion;
    conductores: ConductorOption[];
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
    /** Lo que ya viene elegido al llegar desde «Sin asignar» o de liberar. */
    preseleccion?: Preseleccion;
};

/** Ids que llegan por la URL para no volver a buscar lo que ya se eligió. */
export type Preseleccion = {
    tracto_id: number | null;
    carreta_id: number | null;
    conductor_id: number | null;
};

type FormData = {
    conductor_id: string;
    tracto_id: string;
    carreta_id: string | null;
    desde: string;
    observaciones: string;
};

export function AsignacionForm({
    mode,
    asignacion,
    conductores,
    tractos,
    carretas,
    preseleccion,
}: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        conductor_id: idInicial(
            asignacion?.conductor_id,
            preseleccion?.conductor_id,
        ),
        tracto_id: idInicial(asignacion?.tracto_id, preseleccion?.tracto_id),
        carreta_id:
            idInicial(asignacion?.carreta_id, preseleccion?.carreta_id) || null,
        // Al registrar la estampa el servidor; solo se edita para corregirla.
        desde: asignacion?.desde ?? '',
        observaciones: asignacion?.observaciones ?? '',
    });

    const conductorElegido = conductores.find(
        (conductor) => String(conductor.id) === data.conductor_id,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (asignacion) {
            put(update(asignacion.id).url);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Unidad
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Solo aparecen los conductores y fierros que no están
                        asignados a otra unidad.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Conductor"
                        error={errors.conductor_id}
                        required
                    >
                        {(id) => (
                            <Select
                                value={data.conductor_id}
                                onValueChange={(value) =>
                                    setData('conductor_id', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Seleccionar conductor" />
                                </SelectTrigger>
                                <SelectContent>
                                    {conductores.map((conductor) => (
                                        <SelectItem
                                            key={conductor.id}
                                            value={String(conductor.id)}
                                        >
                                            {conductor.nombre_completo}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    <div className="grid gap-1.5">
                        <Label>Celular</Label>
                        <div className="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm text-muted-foreground tabular-nums">
                            {conductorElegido?.telefono ??
                                'Se toma del padrón de conductores'}
                        </div>
                    </div>

                    <Field label="Tracto" error={errors.tracto_id} required>
                        {(id) => (
                            <Select
                                value={data.tracto_id}
                                onValueChange={(value) =>
                                    setData('tracto_id', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Seleccionar tracto" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tractos.map((tracto) => (
                                        <SelectItem
                                            key={tracto.id}
                                            value={String(tracto.id)}
                                        >
                                            {formatearPlaca(tracto.placa)} —{' '}
                                            {tracto.descripcion}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    <Field label="Carreta (opcional)" error={errors.carreta_id}>
                        {(id) => (
                            <Select
                                value={data.carreta_id ?? SIN_CARRETA}
                                onValueChange={(value) =>
                                    setData(
                                        'carreta_id',
                                        value === SIN_CARRETA ? null : value,
                                    )
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Sin carreta" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_CARRETA}>
                                        Sin carreta
                                    </SelectItem>
                                    {carretas.map((carreta) => (
                                        <SelectItem
                                            key={carreta.id}
                                            value={String(carreta.id)}
                                        >
                                            {formatearPlaca(carreta.placa)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    {mode === 'edit' && (
                        <Field label="Asignada desde" error={errors.desde}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="date"
                                    value={data.desde}
                                    onChange={(e) =>
                                        setData('desde', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                    )}

                    <div className="sm:col-span-2">
                        <Field
                            label="Observaciones"
                            error={errors.observaciones}
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={3}
                                    value={data.observaciones}
                                    onChange={(e) =>
                                        setData('observaciones', e.target.value)
                                    }
                                    placeholder="Notas sobre la unidad (opcional)"
                                />
                            )}
                        </Field>
                    </div>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={asignaciones.index()}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {mode === 'create' ? 'Asignar unidad' : 'Guardar cambios'}
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

/**
 * El valor inicial de un selector: manda lo que ya tiene la asignación al
 * editar y, si no, lo que venga preseleccionado por la URL.
 */
function idInicial(
    actual: number | null | undefined,
    preseleccionado: number | null | undefined,
): string {
    return String(actual ?? preseleccionado ?? '');
}
