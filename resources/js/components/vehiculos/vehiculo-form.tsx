import { Link, useForm } from '@inertiajs/react';
import { useId, useMemo } from 'react';
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
import type {
    ConductorOption,
    DispositivoGpsOption,
    EnumOption,
    SucursalOption,
    Vehiculo,
} from '@/types/fleet';

const SIN_CONDUCTOR = 'none';

type Props = {
    mode: 'create' | 'edit';
    vehiculo?: Vehiculo;
    sucursales: SucursalOption[];
    conductores: ConductorOption[];
    tipos: EnumOption[];
    combustibles: EnumOption[];
    estados: EnumOption[];
    dispositivosGps: DispositivoGpsOption[];
};

type FormData = {
    sucursal_id: number | '';
    conductor_id: number | null;
    placa: string;
    marca: string;
    modelo: string;
    anio: number;
    color: string;
    numero_serie: string;
    numero_motor: string;
    tipo: string;
    combustible: string;
    estado: string;
    kilometraje: number;
    fecha_adquisicion: string;
    observaciones: string;
    imei: string;
};

export function VehiculoForm({
    mode,
    vehiculo,
    sucursales,
    conductores,
    tipos,
    combustibles,
    estados,
    dispositivosGps,
}: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        sucursal_id: vehiculo?.sucursal_id ?? '',
        conductor_id: vehiculo?.conductor_id ?? null,
        placa: vehiculo?.placa ?? '',
        marca: vehiculo?.marca ?? '',
        modelo: vehiculo?.modelo ?? '',
        anio: vehiculo?.anio ?? new Date().getFullYear(),
        color: vehiculo?.color ?? '',
        numero_serie: vehiculo?.numero_serie ?? '',
        numero_motor: vehiculo?.numero_motor ?? '',
        tipo: vehiculo?.tipo ?? 'camioneta',
        combustible: vehiculo?.combustible ?? 'diesel',
        estado: vehiculo?.estado ?? 'activo',
        kilometraje: vehiculo?.kilometraje ?? 0,
        fecha_adquisicion: vehiculo?.fecha_adquisicion ?? '',
        observaciones: vehiculo?.observaciones ?? '',
        imei: vehiculo?.imei ?? '',
    });

    const datalistId = useId();

    const conductoresDeSucursal = useMemo(
        () =>
            conductores.filter(
                (conductor) => conductor.sucursal_id === data.sucursal_id,
            ),
        [conductores, data.sucursal_id],
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (mode === 'create') {
            post(store().url);
        } else if (vehiculo) {
            put(update(vehiculo.id).url);
        }
    };

    const volver = vehiculo ? show(vehiculo.id) : vehiculos.index();

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <Section
                title="Datos generales"
                description="Identificación y características del vehículo."
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
                            onChange={(value) => setData('tipo', value)}
                            options={tipos}
                        />
                    )}
                </Field>
                <Field label="Marca" error={errors.marca} required>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.marca}
                            onChange={(e) => setData('marca', e.target.value)}
                            placeholder="Toyota"
                        />
                    )}
                </Field>
                <Field label="Modelo" error={errors.modelo} required>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.modelo}
                            onChange={(e) => setData('modelo', e.target.value)}
                            placeholder="Hilux"
                        />
                    )}
                </Field>
                <Field label="Año" error={errors.anio} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.anio}
                            onChange={(e) =>
                                setData('anio', Number(e.target.value))
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
                            onChange={(e) => setData('color', e.target.value)}
                            placeholder="Negro"
                        />
                    )}
                </Field>
                <Field label="Combustible" error={errors.combustible} required>
                    {(id) => (
                        <SelectField
                            id={id}
                            value={data.combustible}
                            onChange={(value) => setData('combustible', value)}
                            options={combustibles}
                        />
                    )}
                </Field>
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
                <Field label="Kilometraje" error={errors.kilometraje} required>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            value={data.kilometraje}
                            onChange={(e) =>
                                setData('kilometraje', Number(e.target.value))
                            }
                            min={0}
                        />
                    )}
                </Field>
                <Field label="N.° de serie (VIN)" error={errors.numero_serie}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.numero_serie}
                            onChange={(e) =>
                                setData('numero_serie', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field label="N.° de motor" error={errors.numero_motor}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.numero_motor}
                            onChange={(e) =>
                                setData('numero_motor', e.target.value)
                            }
                        />
                    )}
                </Field>
            </Section>

            <Section
                title="Asignación"
                description="Sucursal a la que pertenece y conductor responsable."
            >
                <Field label="Sucursal" error={errors.sucursal_id} required>
                    {(id) => (
                        <Select
                            value={
                                data.sucursal_id ? String(data.sucursal_id) : ''
                            }
                            onValueChange={(value) => {
                                setData('sucursal_id', Number(value));
                                setData('conductor_id', null);
                            }}
                        >
                            <SelectTrigger id={id} className="w-full">
                                <SelectValue placeholder="Selecciona una sucursal" />
                            </SelectTrigger>
                            <SelectContent>
                                {sucursales.map((sucursal) => (
                                    <SelectItem
                                        key={sucursal.id}
                                        value={String(sucursal.id)}
                                    >
                                        {sucursal.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </Field>
                <Field label="Conductor" error={errors.conductor_id}>
                    {(id) => (
                        <Select
                            value={
                                data.conductor_id
                                    ? String(data.conductor_id)
                                    : SIN_CONDUCTOR
                            }
                            onValueChange={(value) =>
                                setData(
                                    'conductor_id',
                                    value === SIN_CONDUCTOR
                                        ? null
                                        : Number(value),
                                )
                            }
                            disabled={!data.sucursal_id}
                        >
                            <SelectTrigger id={id} className="w-full">
                                <SelectValue
                                    placeholder={
                                        data.sucursal_id
                                            ? 'Sin asignar'
                                            : 'Elige una sucursal primero'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SIN_CONDUCTOR}>
                                    Sin asignar
                                </SelectItem>
                                {conductoresDeSucursal.map((conductor) => (
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
            </Section>

            <Section
                title="Adquisición"
                description="Fecha en que ingresó a la flota. Los documentos (SOAT, revisión técnica, etc.) se gestionan desde el detalle del vehículo."
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
                title="Dispositivo GPS"
                description="Opcional. Vincula este vehículo a su rastreador/dashcam Tracksolid por su IMEI. Déjalo vacío si el vehículo no tiene GPS."
            >
                <Field label="IMEI del dispositivo" error={errors.imei}>
                    {(id) => (
                        <>
                            <Input
                                id={id}
                                value={data.imei}
                                onChange={(e) =>
                                    setData('imei', e.target.value.trim())
                                }
                                list={datalistId}
                                inputMode="numeric"
                                placeholder="860112070376688"
                            />
                            <datalist id={datalistId}>
                                {dispositivosGps.map((dispositivo) => (
                                    <option
                                        key={dispositivo.imei}
                                        value={dispositivo.imei}
                                    >
                                        {dispositivo.label}
                                    </option>
                                ))}
                            </datalist>
                        </>
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
                <Button
                    type="submit"
                    disabled={processing}
                    className="bg-emerald-800 hover:bg-emerald-900"
                >
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
