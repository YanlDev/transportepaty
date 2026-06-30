import { useForm } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/CargaCombustibleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CargaCombustible } from '@/types/fleet';

type FormData = {
    fecha_carga: string;
    odometro: string;
    galones: string;
    costo_total: string;
    observaciones: string;
};

type Props = {
    vehiculoId: number;
    carga: CargaCombustible;
    odometroSugerido: number;
    onClose: () => void;
};

export function ProcesarCargaDialog({
    vehiculoId,
    carga,
    odometroSugerido,
    onClose,
}: Props) {
    const { data, setData, put, processing, errors } = useForm<FormData>({
        fecha_carga: carga.fecha_carga.slice(0, 10),
        odometro: String(carga.odometro ?? odometroSugerido),
        galones: carga.galones !== null ? String(carga.galones) : '',
        costo_total:
            carga.costo_total !== null ? String(carga.costo_total) : '',
        observaciones: carga.observaciones ?? '',
    });

    const galonesNum = parseFloat(data.galones);
    const costoNum = parseFloat(data.costo_total);
    const precioDerivado =
        galonesNum > 0 && costoNum > 0
            ? (costoNum / galonesNum).toFixed(3)
            : null;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        put(update([vehiculoId, carga.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {carga.procesada
                                ? 'Editar carga'
                                : 'Procesar carga'}
                        </DialogTitle>
                        <DialogDescription>
                            Lee las fotos y completa los datos de la carga.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 md:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <FotoGrande
                                url={carga.comprobante_url}
                                titulo="Comprobante"
                            />
                            <FotoGrande
                                url={carga.odometro_foto_url}
                                titulo="Odómetro"
                            />
                        </div>

                        <div className="grid content-start gap-3">
                            <div className="grid gap-1.5">
                                <Label>Fecha de la carga</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_carga}
                                    onChange={(e) =>
                                        setData('fecha_carga', e.target.value)
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
                                        setData('costo_total', e.target.value)
                                    }
                                    placeholder="Total del comprobante"
                                />
                                <InputError message={errors.costo_total} />
                                {precioDerivado && (
                                    <p className="text-xs text-muted-foreground">
                                        Precio por galón: S/ {precioDerivado}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Observaciones — opcional</Label>
                                <Input
                                    value={data.observaciones}
                                    onChange={(e) =>
                                        setData('observaciones', e.target.value)
                                    }
                                    placeholder="Grifo, nota, etc."
                                />
                                <InputError message={errors.observaciones} />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function FotoGrande({ url, titulo }: { url: string | null; titulo: string }) {
    if (!url) {
        return (
            <div className="flex aspect-video items-center justify-center rounded-lg bg-muted text-sm text-muted-foreground">
                <Receipt className="mr-2 size-4" />
                Sin {titulo.toLowerCase()}
            </div>
        );
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            title={`Ver ${titulo} completo`}
            className="block overflow-hidden rounded-lg border border-border"
        >
            <img
                src={url}
                alt={titulo}
                className="max-h-48 w-full bg-muted object-contain"
            />
        </a>
    );
}
