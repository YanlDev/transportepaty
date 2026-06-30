import { useForm } from '@inertiajs/react';
import { Camera, Gauge, Plus, Receipt } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/CargaCombustibleController';
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
import { Spinner } from '@/components/ui/spinner';

type FormData = {
    fecha_carga: string;
    odometro: string;
    galones: string;
    costo_total: string;
    comprobante: File | null;
    odometro_foto: File | null;
};

type Props = {
    vehiculoId: number;
    /** Registro directo (admin): ingresa los datos; las fotos son opcionales. */
    directo?: boolean;
    odometroSugerido?: number;
    /** Compact icon-only trigger (for tight headers like the vehicle page). */
    compacto?: boolean;
};

export function RegistrarCargaDialog({
    vehiculoId,
    directo = false,
    odometroSugerido,
    compacto,
}: Props) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            fecha_carga: new Date().toISOString().slice(0, 10),
            odometro:
                directo && odometroSugerido ? String(odometroSugerido) : '',
            galones: '',
            costo_total: '',
            comprobante: null,
            odometro_foto: null,
        });

    const galonesNum = parseFloat(data.galones);
    const costoNum = parseFloat(data.costo_total);
    const precioDerivado =
        directo && galonesNum > 0 && costoNum > 0
            ? (costoNum / galonesNum).toFixed(3)
            : null;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(store(vehiculoId).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                {compacto ? (
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        title="Registrar carga"
                    >
                        <Plus className="size-4" />
                    </Button>
                ) : (
                    <Button className="bg-emerald-800 hover:bg-emerald-900">
                        <Plus className="size-4" />
                        Registrar carga
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            Registrar carga de combustible
                        </DialogTitle>
                        <DialogDescription>
                            {directo
                                ? 'Ingresa los datos de la carga. Las fotos son opcionales.'
                                : 'Toma una foto del comprobante y otra del odómetro (tablero). Un administrador completará los datos.'}
                        </DialogDescription>
                    </DialogHeader>

                    {directo ? (
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label>Fecha de la carga</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_carga}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_carga',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.fecha_carga} />
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
                                        placeholder="Lectura del tablero"
                                    />
                                    <InputError message={errors.odometro} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Galones</Label>
                                    <Input
                                        type="number"
                                        step="0.001"
                                        inputMode="decimal"
                                        value={data.galones}
                                        onChange={(e) =>
                                            setData('galones', e.target.value)
                                        }
                                        placeholder="Ej. 8.5"
                                    />
                                    <InputError message={errors.galones} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Costo total (S/) — opcional</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        inputMode="decimal"
                                        value={data.costo_total}
                                        onChange={(e) =>
                                            setData(
                                                'costo_total',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Total del comprobante"
                                    />
                                    <InputError message={errors.costo_total} />
                                    {precioDerivado && (
                                        <p className="text-xs text-muted-foreground">
                                            Precio por galón: S/{' '}
                                            {precioDerivado}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <CapturaFoto
                                    icon={<Receipt className="size-5" />}
                                    label="Comprobante (opcional)"
                                    archivo={data.comprobante}
                                    onChange={(file) =>
                                        setData('comprobante', file)
                                    }
                                    error={errors.comprobante}
                                />
                                <CapturaFoto
                                    icon={<Gauge className="size-5" />}
                                    label="Odómetro (opcional)"
                                    archivo={data.odometro_foto}
                                    onChange={(file) =>
                                        setData('odometro_foto', file)
                                    }
                                    error={errors.odometro_foto}
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-4 py-4 sm:grid-cols-2">
                            <CapturaFoto
                                icon={<Receipt className="size-5" />}
                                label="Comprobante"
                                archivo={data.comprobante}
                                onChange={(file) =>
                                    setData('comprobante', file)
                                }
                                error={errors.comprobante}
                            />
                            <CapturaFoto
                                icon={<Gauge className="size-5" />}
                                label="Odómetro (tablero)"
                                archivo={data.odometro_foto}
                                onChange={(file) =>
                                    setData('odometro_foto', file)
                                }
                                error={errors.odometro_foto}
                            />
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            {directo ? 'Registrar' : 'Subir carga'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CapturaFoto({
    icon,
    label,
    archivo,
    onChange,
    error,
}: {
    icon: React.ReactNode;
    label: string;
    archivo: File | null;
    onChange: (file: File | null) => void;
    error?: string;
}) {
    const preview = archivo ? URL.createObjectURL(archivo) : null;

    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <label className="relative flex aspect-[4/3] cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-dashed border-border bg-muted/40 text-muted-foreground hover:bg-muted">
                {preview ? (
                    <img
                        src={preview}
                        alt={label}
                        className="size-full object-cover"
                    />
                ) : (
                    <span className="flex flex-col items-center gap-1 text-xs">
                        {icon}
                        <span className="flex items-center gap-1">
                            <Camera className="size-3.5" />
                            Tomar foto
                        </span>
                    </span>
                )}
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="absolute inset-0 cursor-pointer opacity-0"
                    onChange={(e) => onChange(e.target.files?.[0] ?? null)}
                />
            </label>
            <InputError message={error} />
        </div>
    );
}
