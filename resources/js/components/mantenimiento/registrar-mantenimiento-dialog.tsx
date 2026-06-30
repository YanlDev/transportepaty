import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/MantenimientoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import type { Mantenimiento, PlantillaOption } from '@/types/fleet';

type ItemForm = {
    plantilla_id: string;
    nombre: string;
    tipo_mantenimiento: string;
    costo: string;
};

type FormData = {
    fecha_realizado: string;
    odometro: string;
    proveedor: string;
    factura_numero: string;
    costo_total: string;
    observaciones: string;
    conductor_id: string;
    items: ItemForm[];
    comprobante: File | null;
    fotos: File[];
};

const TIPOS_MANTENIMIENTO = [
    { value: 'preventivo', label: 'Preventivo' },
    { value: 'correctivo', label: 'Correctivo' },
    { value: 'predictivo', label: 'Predictivo' },
];

type Props = {
    vehiculoId: number;
    odometroMinimo: number;
    plantillas: PlantillaOption[];
    /** When provided, the dialog opens in edit mode. */
    mantenimiento?: Mantenimiento | null;
    onClose?: () => void;
};

export function RegistrarMantenimientoDialog({
    vehiculoId,
    odometroMinimo,
    plantillas,
    mantenimiento: existente,
    onClose,
}: Props) {
    const esEdicion = !!existente;
    const [open, setOpen] = useState(false);

    const formDefaultItems: ItemForm[] = existente
        ? existente.items.map((i) => ({
              plantilla_id:
                  i.plantilla_id !== null ? String(i.plantilla_id) : '',
              nombre: i.nombre,
              tipo_mantenimiento: i.tipo_mantenimiento,
              costo: i.costo !== null ? String(i.costo) : '',
          }))
        : [
              {
                  plantilla_id: '',
                  nombre: '',
                  tipo_mantenimiento: 'preventivo',
                  costo: '',
              },
          ];

    const {
        data,
        setData,
        transform,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<FormData>({
        fecha_realizado:
            existente?.fecha_realizado?.slice(0, 10) ??
            new Date().toISOString().slice(0, 10),
        odometro: existente
            ? String(existente.odometro)
            : String(odometroMinimo),
        proveedor: existente?.proveedor ?? '',
        factura_numero: existente?.factura_numero ?? '',
        costo_total:
            existente?.costo_total !== null &&
            existente?.costo_total !== undefined
                ? String(existente.costo_total)
                : '',
        observaciones: existente?.observaciones ?? '',
        conductor_id: '',
        items: formDefaultItems,
        comprobante: null,
        fotos: [],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Los ítems "libres" van sin plantilla_id (null), no cadena vacía.
        transform((payload) => ({
            ...payload,
            items: payload.items.map((item) => ({
                ...item,
                plantilla_id:
                    item.plantilla_id === '' ? null : item.plantilla_id,
            })),
        }));

        const options = {
            preserveScroll: true as const,
            forceFormData: true as const,
            onSuccess: () => {
                if (!esEdicion) {
                    reset();
                }

                setOpen(false);
                onClose?.();
            },
        };

        if (esEdicion && existente) {
            put(update([vehiculoId, existente.id]).url, options);
        } else {
            post(store(vehiculoId).url, options);
        }
    };

    const agregarItem = () => {
        setData('items', [
            ...data.items,
            {
                plantilla_id: '',
                nombre: '',
                tipo_mantenimiento: 'preventivo',
                costo: '',
            },
        ]);
    };

    const elegirPlantilla = (idx: number, value: string) => {
        setData(
            'items',
            data.items.map((item, i) => {
                if (i !== idx) {
                    return item;
                }

                if (value === 'libre') {
                    return { ...item, plantilla_id: '' };
                }

                const plantilla = plantillas.find(
                    (p) => String(p.id) === value,
                );

                return {
                    ...item,
                    plantilla_id: value,
                    nombre: plantilla?.nombre ?? item.nombre,
                    tipo_mantenimiento:
                        plantilla?.tipo_mantenimiento ??
                        item.tipo_mantenimiento,
                };
            }),
        );
    };

    const eliminarItem = (idx: number) => {
        setData(
            'items',
            data.items.filter((_, i) => i !== idx),
        );
    };

    const actualizarItem = (
        idx: number,
        campo: keyof ItemForm,
        valor: string,
    ) => {
        setData(
            'items',
            data.items.map((item, i) =>
                i === idx ? { ...item, [campo]: valor } : item,
            ),
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                    clearErrors();
                    onClose?.();
                }
            }}
        >
            <DialogTrigger asChild>
                {esEdicion ? null : (
                    <Button className="bg-emerald-800 hover:bg-emerald-900">
                        <Plus className="size-4" />
                        Registrar mantenimiento
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {esEdicion
                                ? 'Editar mantenimiento'
                                : 'Registrar mantenimiento'}
                        </DialogTitle>
                        <DialogDescription>
                            {esEdicion
                                ? 'Actualiza los datos del mantenimiento.'
                                : 'Completa los datos del servicio realizado.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 md:grid-cols-2">
                        <div className="grid gap-3">
                            <div className="grid gap-1.5">
                                <Label>Fecha del servicio</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_realizado}
                                    onChange={(e) =>
                                        setData(
                                            'fecha_realizado',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.fecha_realizado} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Odómetro (km)</Label>
                                <Input
                                    type="number"
                                    inputMode="numeric"
                                    value={data.odometro}
                                    onChange={(e) =>
                                        setData('odometro', e.target.value)
                                    }
                                />
                                <InputError message={errors.odometro} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Proveedor / Taller — opcional</Label>
                                <Input
                                    value={data.proveedor}
                                    onChange={(e) =>
                                        setData('proveedor', e.target.value)
                                    }
                                    placeholder="Nombre del taller"
                                />
                                <InputError message={errors.proveedor} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Factura n.° — opcional</Label>
                                <Input
                                    value={data.factura_numero}
                                    onChange={(e) =>
                                        setData(
                                            'factura_numero',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Número de factura"
                                />
                                <InputError message={errors.factura_numero} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Costo total (S/) — opcional</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    inputMode="decimal"
                                    value={data.costo_total}
                                    onChange={(e) =>
                                        setData('costo_total', e.target.value)
                                    }
                                    placeholder="Total (si no se detalla por ítem)"
                                />
                                <InputError message={errors.costo_total} />
                            </div>
                        </div>

                        <div className="grid content-start gap-3">
                            <div className="grid gap-1.5">
                                <Label>Observaciones — opcional</Label>
                                <Textarea
                                    value={data.observaciones}
                                    onChange={(e) =>
                                        setData('observaciones', e.target.value)
                                    }
                                    placeholder="Notas adicionales"
                                    rows={3}
                                />
                                <InputError message={errors.observaciones} />
                            </div>

                            {!esEdicion && (
                                <div className="grid gap-1.5">
                                    <Label>Comprobante — opcional</Label>
                                    <Input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) =>
                                            setData(
                                                'comprobante',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError message={errors.comprobante} />
                                </div>
                            )}

                            {!esEdicion && (
                                <div className="grid gap-1.5">
                                    <Label>Fotos del servicio — opcional</Label>
                                    <Input
                                        type="file"
                                        multiple
                                        accept="image/*"
                                        onChange={(e) =>
                                            setData(
                                                'fotos',
                                                Array.from(
                                                    e.target.files ?? [],
                                                ),
                                            )
                                        }
                                    />
                                    <InputError message={errors.fotos} />
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="border-t border-border pt-4">
                        <div className="mb-3 flex items-center justify-between">
                            <Label>Detalle de servicios realizados</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={agregarItem}
                            >
                                <Plus className="size-3.5" />
                                Agregar ítem
                            </Button>
                        </div>

                        <div className="flex flex-col gap-2">
                            {data.items.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-start gap-2 rounded-lg border border-border p-3"
                                >
                                    <div className="grid flex-1 gap-2 sm:grid-cols-3">
                                        <div className="grid gap-1.5 sm:col-span-2">
                                            <Select
                                                value={
                                                    item.plantilla_id === ''
                                                        ? 'libre'
                                                        : item.plantilla_id
                                                }
                                                onValueChange={(v) =>
                                                    elegirPlantilla(idx, v)
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Servicio" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {plantillas.map((p) => (
                                                        <SelectItem
                                                            key={p.id}
                                                            value={String(p.id)}
                                                        >
                                                            {p.nombre}
                                                        </SelectItem>
                                                    ))}
                                                    <SelectItem value="libre">
                                                        Otro (libre)
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors[
                                                        `items.${idx}.plantilla_id`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                inputMode="decimal"
                                                placeholder="Costo S/"
                                                value={item.costo}
                                                onChange={(e) =>
                                                    actualizarItem(
                                                        idx,
                                                        'costo',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[`items.${idx}.costo`]
                                                }
                                            />
                                        </div>
                                        {item.plantilla_id === '' && (
                                            <>
                                                <div className="grid gap-1.5 sm:col-span-2">
                                                    <Input
                                                        placeholder="Nombre del servicio"
                                                        value={item.nombre}
                                                        onChange={(e) =>
                                                            actualizarItem(
                                                                idx,
                                                                'nombre',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `items.${idx}.nombre`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                                <div>
                                                    <Select
                                                        value={
                                                            item.tipo_mantenimiento
                                                        }
                                                        onValueChange={(v) =>
                                                            actualizarItem(
                                                                idx,
                                                                'tipo_mantenimiento',
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {TIPOS_MANTENIMIENTO.map(
                                                                (t) => (
                                                                    <SelectItem
                                                                        key={
                                                                            t.value
                                                                        }
                                                                        value={
                                                                            t.value
                                                                        }
                                                                    >
                                                                        {
                                                                            t.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                    {data.items.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 shrink-0 text-destructive"
                                            onClick={() => eliminarItem(idx)}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <DialogFooter className="mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setOpen(false);
                                reset();
                                clearErrors();
                                onClose?.();
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {esEdicion ? 'Guardar cambios' : 'Registrar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
