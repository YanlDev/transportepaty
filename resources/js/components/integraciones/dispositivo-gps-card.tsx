import { Link, router, useForm } from '@inertiajs/react';
import {
    Car,
    Gauge,
    Link2,
    Link2Off,
    RefreshCw,
    User,
    Video,
} from 'lucide-react';
import { useState } from 'react';
import {
    desvincular,
    importar,
    sincronizar,
    vincular,
} from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import { show as vehiculoShow } from '@/actions/App/Http/Controllers/VehiculoController';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type {
    DispositivoGps,
    SucursalOption,
    VehiculoDisponible,
} from '@/types/fleet';

type Props = {
    dispositivo: DispositivoGps;
    vehiculosDisponibles: VehiculoDisponible[];
    sucursales: SucursalOption[];
};

export function DispositivoGpsCard({
    dispositivo,
    vehiculosDisponibles,
    sucursales,
}: Props) {
    const vinculado = dispositivo.vehiculo !== null;

    return (
        <article className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-semibold text-foreground">
                            {dispositivo.modelo ?? 'Dispositivo GPS'}
                        </span>
                        {dispositivo.es_dashcam && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-600/20">
                                <Video className="size-3" />
                                Dashcam
                            </span>
                        )}
                    </div>
                    <p className="font-mono text-xs text-muted-foreground">
                        IMEI {dispositivo.imei}
                    </p>
                </div>
                <span
                    className={
                        dispositivo.activo
                            ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/20'
                            : 'inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                    }
                >
                    <span
                        className={`size-1.5 rounded-full ${dispositivo.activo ? 'bg-emerald-500' : 'bg-zinc-400'}`}
                    />
                    {dispositivo.activo ? 'Activo' : 'Inactivo'}
                </span>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <Campo
                    icon={<Car className="size-3.5" />}
                    label="Placa"
                    value={dispositivo.placa}
                />
                <Campo
                    label="Vehículo"
                    value={
                        [dispositivo.marca, dispositivo.modelo_vehiculo]
                            .filter(Boolean)
                            .join(' ') || null
                    }
                />
                <Campo
                    icon={<User className="size-3.5" />}
                    label="Conductor"
                    value={dispositivo.conductor}
                />
                <Campo
                    icon={<Gauge className="size-3.5" />}
                    label="Kilometraje"
                    value={
                        dispositivo.kilometraje !== null
                            ? `${dispositivo.kilometraje.toLocaleString('es-PE')} km`
                            : null
                    }
                />
            </dl>

            <div className="mt-auto border-t border-border pt-4">
                {vinculado ? (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link
                            href={vehiculoShow(dispositivo.vehiculo!.id)}
                            className="text-sm font-medium text-emerald-800 hover:underline"
                        >
                            {dispositivo.vehiculo!.placa} ·{' '}
                            {dispositivo.vehiculo!.marca}{' '}
                            {dispositivo.vehiculo!.modelo}
                        </Link>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        sincronizar(dispositivo.vehiculo!.id)
                                            .url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw className="size-4" />
                                Sincronizar
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() =>
                                    router.delete(
                                        desvincular(dispositivo.vehiculo!.id)
                                            .url,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Link2Off className="size-4" />
                                Desvincular
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-2">
                        <VincularDialog
                            dispositivo={dispositivo}
                            vehiculosDisponibles={vehiculosDisponibles}
                        />
                        <ImportarDialog
                            dispositivo={dispositivo}
                            sucursales={sucursales}
                        />
                    </div>
                )}
            </div>
        </article>
    );
}

function Campo({
    icon,
    label,
    value,
}: {
    icon?: React.ReactNode;
    label: string;
    value: string | null;
}) {
    return (
        <div>
            <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {icon}
                {label}
            </dt>
            <dd className="mt-0.5 font-medium text-foreground">
                {value ?? '—'}
            </dd>
        </div>
    );
}

function VincularDialog({
    dispositivo,
    vehiculosDisponibles,
}: {
    dispositivo: DispositivoGps;
    vehiculosDisponibles: VehiculoDisponible[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        imei: dispositivo.imei,
        vehiculo_id: dispositivo.vehiculo_sugerido_id
            ? String(dispositivo.vehiculo_sugerido_id)
            : '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(vincular().url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    className="bg-emerald-800 hover:bg-emerald-900"
                    disabled={vehiculosDisponibles.length === 0}
                >
                    <Link2 className="size-4" />
                    Vincular
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Vincular dispositivo</DialogTitle>
                        <DialogDescription>
                            Asocia el GPS {dispositivo.imei} a un vehículo ya
                            registrado.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-4 space-y-2">
                        <Label htmlFor="vehiculo_id">Vehículo</Label>
                        <Select
                            value={data.vehiculo_id}
                            onValueChange={(value) =>
                                setData('vehiculo_id', value)
                            }
                        >
                            <SelectTrigger id="vehiculo_id">
                                <SelectValue placeholder="Selecciona un vehículo" />
                            </SelectTrigger>
                            <SelectContent>
                                {vehiculosDisponibles.map((v) => (
                                    <SelectItem key={v.id} value={String(v.id)}>
                                        {v.descripcion}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.vehiculo_id} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.vehiculo_id}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            Vincular
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ImportarDialog({
    dispositivo,
    sucursales,
}: {
    dispositivo: DispositivoGps;
    sucursales: SucursalOption[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        imei: dispositivo.imei,
        sucursal_id: sucursales[0] ? String(sucursales[0].id) : '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(importar().url, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Importar como nuevo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Importar como vehículo</DialogTitle>
                        <DialogDescription>
                            Crea un vehículo nuevo con los datos del GPS
                            {dispositivo.placa ? ` (${dispositivo.placa})` : ''}
                            . Podrás completar el resto después.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-4 space-y-2">
                        <Label htmlFor="sucursal_id">Sucursal</Label>
                        <Select
                            value={data.sucursal_id}
                            onValueChange={(value) =>
                                setData('sucursal_id', value)
                            }
                        >
                            <SelectTrigger id="sucursal_id">
                                <SelectValue placeholder="Selecciona una sucursal" />
                            </SelectTrigger>
                            <SelectContent>
                                {sucursales.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.sucursal_id} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.sucursal_id}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            Importar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
