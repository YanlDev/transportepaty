import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import viajes, {
    storeManual,
} from '@/actions/App/Http/Controllers/ViajeController';
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
    ConductorOption,
    EnumOption,
    VehiculoOption,
} from '@/types/fleet';

// Radix Select no admite items con value vacío, así que usamos un centinela
// para representar "sin carreta".
const SIN_CARRETA = '__sin_carreta__';

type Props = {
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
    conductores: ConductorOption[];
    tiposCarga: EnumOption[];
};

type FormData = {
    numero_gr: string;
    fecha_traslado: string;
    tracto_id: string;
    carreta_id: string | null;
    conductor_id: string;
    cliente: string;
    destinatario: string;
    origen: string;
    direccion_destino: string;
    departamento_destino: string;
    peso: string;
    unidad_peso: string;
    tipo_carga: string;
    observaciones: string;
    archivo: File | null;
};

export function ViajeManualForm({
    tractos,
    carretas,
    conductores,
    tiposCarga,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        numero_gr: '',
        fecha_traslado: new Date().toISOString().slice(0, 10),
        tracto_id: '',
        carreta_id: null,
        conductor_id: '',
        cliente: '',
        destinatario: '',
        origen: '',
        direccion_destino: '',
        departamento_destino: '',
        peso: '',
        unidad_peso: 'TNE',
        tipo_carga: 'particular',
        observaciones: '',
        archivo: null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(storeManual().url);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Documento
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Para viajes donde el remitente ya emitió su propia
                        GRE-Transportista y no corresponde emitir una GRE
                        aparte.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="N° de guía" error={errors.numero_gr} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.numero_gr}
                                onChange={(e) =>
                                    setData('numero_gr', e.target.value)
                                }
                                placeholder="T001-24879"
                            />
                        )}
                    </Field>

                    <Field
                        label="Fecha de traslado"
                        error={errors.fecha_traslado}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.fecha_traslado}
                                onChange={(e) =>
                                    setData('fecha_traslado', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <div className="sm:col-span-2">
                        <Field
                            label="Archivo de referencia (opcional)"
                            error={errors.archivo}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="file"
                                    accept="application/pdf"
                                    onChange={(e) =>
                                        setData(
                                            'archivo',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>
                </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Unidad
                    </h2>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
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

                    <Field
                        label="Tipo de carga"
                        error={errors.tipo_carga}
                        required
                    >
                        {(id) => (
                            <Select
                                value={data.tipo_carga}
                                onValueChange={(value) =>
                                    setData('tipo_carga', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Seleccionar tipo de carga" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tiposCarga.map((opcion) => (
                                        <SelectItem
                                            key={opcion.value}
                                            value={opcion.value}
                                        >
                                            {opcion.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Carga y ruta
                    </h2>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Cliente" error={errors.cliente} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.cliente}
                                onChange={(e) =>
                                    setData('cliente', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <Field
                        label="Destinatario"
                        error={errors.destinatario}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.destinatario}
                                onChange={(e) =>
                                    setData('destinatario', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <div className="sm:col-span-2">
                        <Field label="Origen" error={errors.origen} required>
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={2}
                                    value={data.origen}
                                    onChange={(e) =>
                                        setData('origen', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                    </div>

                    <div className="sm:col-span-2">
                        <Field
                            label="Dirección de destino"
                            error={errors.direccion_destino}
                            required
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={2}
                                    value={data.direccion_destino}
                                    onChange={(e) =>
                                        setData(
                                            'direccion_destino',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Calle, distrito, provincia"
                                />
                            )}
                        </Field>
                    </div>

                    <Field
                        label="Departamento de destino"
                        error={errors.departamento_destino}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.departamento_destino}
                                onChange={(e) =>
                                    setData(
                                        'departamento_destino',
                                        e.target.value,
                                    )
                                }
                                placeholder="MOQUEGUA"
                            />
                        )}
                    </Field>

                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Peso" error={errors.peso} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    value={data.peso}
                                    onChange={(e) =>
                                        setData('peso', e.target.value)
                                    }
                                />
                            )}
                        </Field>

                        <Field
                            label="Unidad"
                            error={errors.unidad_peso}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.unidad_peso}
                                    onChange={(e) =>
                                        setData('unidad_peso', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                    </div>

                    <div className="sm:col-span-2">
                        <Field
                            label="Observaciones"
                            error={errors.observaciones}
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={2}
                                    value={data.observaciones}
                                    onChange={(e) =>
                                        setData('observaciones', e.target.value)
                                    }
                                    placeholder="Notas sobre el viaje (opcional)"
                                />
                            )}
                        </Field>
                    </div>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={viajes.index()}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Registrar viaje
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
