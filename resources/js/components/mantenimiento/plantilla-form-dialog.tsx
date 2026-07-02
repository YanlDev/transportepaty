import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useId } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/PlantillaMantenimientoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import type { EnumOption, PlantillaMantenimiento } from '@/types/fleet';

const TODOS = '__todas__';

type FormData = {
    nombre: string;
    tipo_mantenimiento: string;
    marca: string;
    modelo: string;
    tipo_vehiculo: string;
    intervalo_km: string;
    intervalo_meses: string;
    una_vez: boolean;
    costo_estimado: string;
    orden: string;
    descripcion: string;
    activo: boolean;
};

type Props = {
    tiposVehiculo: EnumOption[];
    plantilla?: PlantillaMantenimiento | null;
    onClose?: () => void;
};

export function PlantillaFormDialog({
    tiposVehiculo,
    plantilla,
    onClose,
}: Props) {
    const esEdicion = !!plantilla;
    const controlado = esEdicion;
    const activoId = useId();
    const unaVezId = useId();

    const form = useForm<FormData>({
        nombre: plantilla?.nombre ?? '',
        tipo_mantenimiento: plantilla?.tipo_mantenimiento ?? '',
        marca: plantilla?.marca ?? '',
        modelo: plantilla?.modelo ?? '',
        tipo_vehiculo: plantilla?.tipo_vehiculo ?? TODOS,
        intervalo_km:
            plantilla?.intervalo_km != null
                ? String(plantilla.intervalo_km)
                : '',
        intervalo_meses:
            plantilla?.intervalo_meses != null
                ? String(plantilla.intervalo_meses)
                : '',
        costo_estimado:
            plantilla?.costo_estimado != null
                ? String(plantilla.costo_estimado)
                : '',
        una_vez: plantilla?.una_vez ?? false,
        orden: plantilla?.orden != null ? String(plantilla.orden) : '0',
        descripcion: plantilla?.descripcion ?? '',
        activo: plantilla?.activo ?? true,
    });

    const { data, setData, processing, errors, reset, clearErrors } = form;

    const cerrar = () => {
        reset();
        clearErrors();
        onClose?.();
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((payload) => ({
            ...payload,
            tipo_vehiculo:
                payload.tipo_vehiculo === TODOS ? '' : payload.tipo_vehiculo,
        }));

        const options = { preserveScroll: true, onSuccess: () => cerrar() };

        if (esEdicion && plantilla) {
            form.put(update(plantilla.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    return (
        <Dialog
            open={controlado ? true : undefined}
            onOpenChange={(value) => {
                if (!value) {
                    cerrar();
                }
            }}
        >
            {!controlado && (
                <DialogTrigger asChild>
                    <Button className="bg-emerald-800 hover:bg-emerald-900">
                        <Plus className="size-4" />
                        Nueva plantilla
                    </Button>
                </DialogTrigger>
            )}
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {esEdicion ? 'Editar plantilla' : 'Nueva plantilla'}
                        </DialogTitle>
                        <DialogDescription>
                            Servicio del plan de mantenimiento. Déjalo sin marca
                            ni modelo para que aplique de forma genérica.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <Campo
                            label="Nombre del servicio"
                            error={errors.nombre}
                            required
                        >
                            <Input
                                value={data.nombre}
                                onChange={(e) =>
                                    setData('nombre', e.target.value)
                                }
                                placeholder="Cambio de aceite + filtro"
                            />
                        </Campo>
                        <Campo
                            label="Tipo"
                            error={errors.tipo_mantenimiento}
                            required
                        >
                            <Input
                                value={data.tipo_mantenimiento}
                                onChange={(e) =>
                                    setData(
                                        'tipo_mantenimiento',
                                        e.target.value,
                                    )
                                }
                                placeholder="aceite, frenos, filtro_aire…"
                            />
                        </Campo>
                        <Campo label="Marca — opcional" error={errors.marca}>
                            <Input
                                value={data.marca}
                                onChange={(e) =>
                                    setData('marca', e.target.value)
                                }
                                placeholder="Toyota"
                            />
                        </Campo>
                        <Campo label="Modelo — opcional" error={errors.modelo}>
                            <Input
                                value={data.modelo}
                                onChange={(e) =>
                                    setData('modelo', e.target.value)
                                }
                                placeholder="Hilux"
                            />
                        </Campo>
                        <Campo
                            label="Tipo de vehículo"
                            error={errors.tipo_vehiculo}
                        >
                            <Select
                                value={data.tipo_vehiculo}
                                onValueChange={(v) =>
                                    setData('tipo_vehiculo', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>
                                        Todos (genérica)
                                    </SelectItem>
                                    {tiposVehiculo.map((t) => (
                                        <SelectItem
                                            key={t.value}
                                            value={t.value}
                                        >
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Campo>
                        <Campo label="Orden" error={errors.orden}>
                            <Input
                                type="number"
                                inputMode="numeric"
                                value={data.orden}
                                onChange={(e) =>
                                    setData('orden', e.target.value)
                                }
                            />
                        </Campo>
                        <Campo
                            label={
                                data.una_vez
                                    ? 'Km objetivo (servicio único)'
                                    : 'Intervalo (km)'
                            }
                            error={errors.intervalo_km}
                        >
                            <Input
                                type="number"
                                inputMode="numeric"
                                value={data.intervalo_km}
                                onChange={(e) =>
                                    setData('intervalo_km', e.target.value)
                                }
                                placeholder={data.una_vez ? '1000' : '5000'}
                            />
                        </Campo>
                        <Campo
                            label="Intervalo (meses)"
                            error={errors.intervalo_meses}
                        >
                            <Input
                                type="number"
                                inputMode="numeric"
                                value={data.intervalo_meses}
                                onChange={(e) =>
                                    setData('intervalo_meses', e.target.value)
                                }
                                placeholder="6"
                            />
                        </Campo>
                        <Campo
                            label="Costo estimado (S/)"
                            error={errors.costo_estimado}
                        >
                            <Input
                                type="number"
                                step="0.01"
                                inputMode="decimal"
                                value={data.costo_estimado}
                                onChange={(e) =>
                                    setData('costo_estimado', e.target.value)
                                }
                            />
                        </Campo>
                        <div className="sm:col-span-2">
                            <Campo
                                label="Descripción — opcional"
                                error={errors.descripcion}
                            >
                                <Textarea
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                    rows={2}
                                />
                            </Campo>
                        </div>
                        <div className="flex items-start gap-2 sm:col-span-2">
                            <Checkbox
                                id={unaVezId}
                                checked={data.una_vez}
                                onCheckedChange={(v) =>
                                    setData('una_vez', v === true)
                                }
                            />
                            <Label htmlFor={unaVezId} className="font-normal">
                                Servicio único (primer mantenimiento)
                                <span className="block text-xs text-muted-foreground">
                                    Aparece una sola vez al llegar al km objetivo
                                    (ej. inspección de los 1000 km en vehículos
                                    nuevos) y no se repite.
                                </span>
                            </Label>
                        </div>
                        <div className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                id={activoId}
                                checked={data.activo}
                                onCheckedChange={(v) =>
                                    setData('activo', v === true)
                                }
                            />
                            <Label htmlFor={activoId} className="font-normal">
                                Plantilla activa
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={cerrar}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            {esEdicion ? 'Guardar cambios' : 'Crear plantilla'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Campo({
    label,
    error,
    required,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>
                {label}
                {required && <span className="text-destructive"> *</span>}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
