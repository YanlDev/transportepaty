import { Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import vehiculos, {
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/VehiculoController';
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
import type { EnumOption, Vehiculo } from '@/types/fleet';

type Props = {
    mode: 'create' | 'edit';
    vehiculo?: Vehiculo;
    /** Solo en `create`: preselecciona el tipo según desde qué listado se entró. */
    tipoInicial?: string;
    tipos: EnumOption[];
    cajas: EnumOption[];
    estados: EnumOption[];
};

type FormData = {
    placa: string;
    marca: string;
    modelo: string;
    anio: number | '';
    tipo: string;
    estado: string;
    caja: string;
    vin: string;
    numero_motor: string;
    color: string;
    ejes: number | '';
    peso_neto: number | '';
    peso_bruto: number | '';
    carga_util: number | '';
    fecha_adquisicion: string;
    observaciones: string;
};

export function VehiculoForm({
    mode,
    vehiculo,
    tipoInicial,
    tipos,
    cajas,
    estados,
}: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        placa: vehiculo?.placa ?? '',
        marca: vehiculo?.marca ?? '',
        modelo: vehiculo?.modelo ?? '',
        anio: vehiculo?.anio ?? '',
        tipo: vehiculo?.tipo ?? tipoInicial ?? 'tracto',
        estado: vehiculo?.estado ?? 'activo',
        caja: vehiculo?.caja ?? '',
        vin: vehiculo?.vin ?? '',
        numero_motor: vehiculo?.numero_motor ?? '',
        color: vehiculo?.color ?? '',
        ejes: vehiculo?.ejes ?? '',
        peso_neto: vehiculo?.peso_neto ?? '',
        peso_bruto: vehiculo?.peso_bruto ?? '',
        carga_util: vehiculo?.carga_util ?? '',
        fecha_adquisicion: vehiculo?.fecha_adquisicion ?? '',
        observaciones: vehiculo?.observaciones ?? '',
    });

    // La carreta es remolcada: no tiene transmisión ni motor propio.
    const esTracto = data.tipo === 'tracto';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (vehiculo) {
            put(update(vehiculo.id).url);
        }
    };

    const volver = vehiculo
        ? show(vehiculo.id)
        : data.tipo === 'carreta'
          ? vehiculos.carretas()
          : vehiculos.tractos();

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <Section
                title="Datos generales"
                description="Identificación y características de la unidad."
            >
                <Field label="Placa" error={errors.placa} required>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.placa}
                            onChange={(e) =>
                                setData('placa', e.target.value.toUpperCase())
                            }
                            placeholder="ABC-123"
                        />
                    )}
                </Field>
                <Field label="Tipo" error={errors.tipo} required>
                    {(id) => (
                        <SelectField
                            id={id}
                            value={data.tipo}
                            onChange={(value) => {
                                setData('tipo', value);

                                if (value !== 'tracto') {
                                    setData('caja', '');
                                }
                            }}
                            options={tipos}
                        />
                    )}
                </Field>
                <Field label="Marca" error={errors.marca}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.marca}
                            onChange={(e) =>
                                setData('marca', e.target.value.toUpperCase())
                            }
                            placeholder="INTERNATIONAL"
                        />
                    )}
                </Field>
                <Field label="Modelo" error={errors.modelo}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.modelo}
                            onChange={(e) =>
                                setData('modelo', e.target.value.toUpperCase())
                            }
                            placeholder="LT625 6X4"
                        />
                    )}
                </Field>
                <Field label="Año" error={errors.anio}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.anio}
                            onChange={(e) =>
                                setData(
                                    'anio',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            min={1950}
                            max={new Date().getFullYear() + 1}
                        />
                    )}
                </Field>
                <Field label="Color" error={errors.color}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.color}
                            onChange={(e) =>
                                setData('color', e.target.value.toUpperCase())
                            }
                            placeholder="BLANCO"
                        />
                    )}
                </Field>
                {esTracto && (
                    <Field label="Caja" error={errors.caja}>
                        {(id) => (
                            <SelectField
                                id={id}
                                value={data.caja}
                                onChange={(value) => setData('caja', value)}
                                options={cajas}
                            />
                        )}
                    </Field>
                )}
                <Field label="Estado" error={errors.estado} required>
                    {(id) => (
                        <SelectField
                            id={id}
                            value={data.estado}
                            onChange={(value) => setData('estado', value)}
                            options={estados}
                        />
                    )}
                </Field>
            </Section>

            <Section
                title="Identificación técnica"
                description="Datos que figuran en la tarjeta de propiedad."
            >
                <Field label="VIN" error={errors.vin}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.vin}
                            onChange={(e) =>
                                setData('vin', e.target.value.toUpperCase())
                            }
                        />
                    )}
                </Field>
                {esTracto && (
                    <Field label="N.° de motor" error={errors.numero_motor}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.numero_motor}
                                onChange={(e) =>
                                    setData(
                                        'numero_motor',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                        )}
                    </Field>
                )}
                <Field label="Ejes" error={errors.ejes}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.ejes}
                            onChange={(e) =>
                                setData(
                                    'ejes',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            min={1}
                            max={10}
                        />
                    )}
                </Field>
            </Section>

            <Section
                title="Pesos"
                description="Expresados en kilogramos, según tarjeta de propiedad."
            >
                <Field label="Peso neto (kg)" error={errors.peso_neto}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.peso_neto}
                            onChange={(e) =>
                                setData(
                                    'peso_neto',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            min={0}
                        />
                    )}
                </Field>
                <Field label="Peso bruto (kg)" error={errors.peso_bruto}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.peso_bruto}
                            onChange={(e) =>
                                setData(
                                    'peso_bruto',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            min={0}
                        />
                    )}
                </Field>
                <Field label="Carga útil (kg)" error={errors.carga_util}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.carga_util}
                            onChange={(e) =>
                                setData(
                                    'carga_util',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                            min={0}
                        />
                    )}
                </Field>
            </Section>

            <Section
                title="Adquisición"
                description="Fecha en que ingresó a la flota. Los documentos (SOAT, TUC, MATPEL, etc.) se gestionan desde el detalle del vehículo."
            >
                <Field
                    label="Fecha de adquisición"
                    error={errors.fecha_adquisicion}
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="date"
                            value={data.fecha_adquisicion}
                            onChange={(e) =>
                                setData('fecha_adquisicion', e.target.value)
                            }
                        />
                    )}
                </Field>
            </Section>

            <Section
                title="Observaciones"
                description="Notas adicionales (opcional)."
            >
                <div className="sm:col-span-2">
                    <Textarea
                        value={data.observaciones}
                        onChange={(e) =>
                            setData('observaciones', e.target.value)
                        }
                        placeholder="Detalles, accesorios, condiciones especiales..."
                        aria-label="Observaciones"
                    />
                    <InputError message={errors.observaciones} />
                </div>
            </Section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={volver}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {mode === 'create'
                        ? 'Registrar vehículo'
                        : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="mb-4">
                <h2 className="text-sm font-semibold text-foreground">
                    {title}
                </h2>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
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

function SelectField({
    id,
    value,
    onChange,
    options,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    options: EnumOption[];
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
