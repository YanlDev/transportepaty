import { Head, Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import asignaciones, {
    reasignar,
} from '@/actions/App/Http/Controllers/AsignacionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import type { VehiculoOption } from '@/types/fleet';

/** Radix Select no admite items con value vacío. */
const SIN_CARRETA = '__sin_carreta__';

type Props = {
    asignacion: {
        id: number;
        conductor_nombre: string;
        tracto_placa: string;
        carreta_placa: string | null;
    };
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
};

/**
 * Mueve al conductor de la unidad actual a otro tracto en un solo paso: por
 * detrás cierra la asignación vigente y abre una nueva, sin que haga falta
 * liberar primero y armar la unidad después por separado. El conductor no se
 * elige acá — es el mismo que ya maneja esta unidad — solo cambia el fierro.
 */
export default function AsignacionReasignar({
    asignacion,
    tractos,
    carretas,
}: Props) {
    const idTracto = useId();
    const idCarreta = useId();
    const idObservaciones = useId();

    const { data, setData, patch, processing, errors } = useForm({
        tracto_id: '',
        carreta_id: null as string | null,
        observaciones: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        patch(reasignar(asignacion.id).url);
    };

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Reasignar a ${asignacion.conductor_nombre}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Reasignar conductor
                </h1>
                <p className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {asignacion.conductor_nombre}
                    </span>{' '}
                    deja {formatearPlaca(asignacion.tracto_placa)}
                    {asignacion.carreta_placa &&
                        ` / ${formatearPlaca(asignacion.carreta_placa)}`}{' '}
                    y pasa a la unidad que elijas abajo.
                </p>
            </div>

            <form onSubmit={submit} className="flex flex-col gap-6">
                <section className="rounded-xl border border-border bg-card p-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor={idTracto}>
                                Nuevo tracto
                                <span className="text-destructive"> *</span>
                            </Label>
                            <Select
                                value={data.tracto_id}
                                onValueChange={(value) =>
                                    setData('tracto_id', value)
                                }
                            >
                                <SelectTrigger id={idTracto}>
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
                            <InputError message={errors.tracto_id} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor={idCarreta}>
                                Carreta (opcional)
                            </Label>
                            <Select
                                value={data.carreta_id ?? SIN_CARRETA}
                                onValueChange={(value) =>
                                    setData(
                                        'carreta_id',
                                        value === SIN_CARRETA ? null : value,
                                    )
                                }
                            >
                                <SelectTrigger id={idCarreta}>
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
                            <InputError message={errors.carreta_id} />
                        </div>

                        <div className="sm:col-span-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor={idObservaciones}>
                                    Observaciones
                                </Label>
                                <Textarea
                                    id={idObservaciones}
                                    rows={3}
                                    value={data.observaciones}
                                    onChange={(event) =>
                                        setData(
                                            'observaciones',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Notas sobre el cambio (opcional)"
                                />
                                <InputError message={errors.observaciones} />
                            </div>
                        </div>
                    </div>
                </section>

                <div className="flex items-center justify-end gap-3 border-t pt-4">
                    <Button asChild variant="outline" type="button">
                        <Link href={asignaciones.index()}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Reasignar
                    </Button>
                </div>
            </form>
        </div>
    );
}

AsignacionReasignar.layout = {
    breadcrumbs: [
        { title: 'Asignaciones', href: asignaciones.index().url },
        { title: 'Reasignar', href: '#' },
    ],
};
