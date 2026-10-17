import { useForm } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { actualizarParametros } from '@/actions/App/Http/Controllers/LlantaController';
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
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { UmbralesLlanta } from '@/types/llantas';

/**
 * Hasta cuántos mm de dibujo una llanta sale ámbar y hasta cuántos roja.
 * Vale para toda la flota y recolorea todo al guardar.
 */
export function CortesLlantasDialog({
    umbrales,
}: {
    umbrales: UmbralesLlanta;
}) {
    const [abierto, setAbierto] = useState(false);

    const { data, setData, put, transform, processing, errors } = useForm({
        llantas_revisar_mm: String(umbrales.revisar_mm),
        llantas_cambiar_mm: String(umbrales.cambiar_mm),
    });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            llantas_revisar_mm: datos.llantas_revisar_mm.replace(',', '.'),
            llantas_cambiar_mm: datos.llantas_cambiar_mm.replace(',', '.'),
        }));

        put(actualizarParametros().url, {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <SlidersHorizontal className="size-4" />
                    Ajustar cortes
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Cortes del semáforo</DialogTitle>
                        <DialogDescription>
                            Según la profundidad del dibujo en la última
                            medición. Vale para todas las unidades.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="corte-revisar">
                                Revisar desde (mm)
                            </Label>
                            <Input
                                id="corte-revisar"
                                inputMode="decimal"
                                value={data.llantas_revisar_mm}
                                onChange={(e) =>
                                    setData(
                                        'llantas_revisar_mm',
                                        e.target.value,
                                    )
                                }
                                className="tabular-nums"
                            />
                            <InputError message={errors.llantas_revisar_mm} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="corte-cambiar">
                                Cambiar desde (mm)
                            </Label>
                            <Input
                                id="corte-cambiar"
                                inputMode="decimal"
                                value={data.llantas_cambiar_mm}
                                onChange={(e) =>
                                    setData(
                                        'llantas_cambiar_mm',
                                        e.target.value,
                                    )
                                }
                                className="tabular-nums"
                            />
                            <InputError message={errors.llantas_cambiar_mm} />
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Con {data.llantas_revisar_mm || '—'} mm o menos la
                        llanta sale ámbar; con {data.llantas_cambiar_mm || '—'}{' '}
                        mm o menos, roja.
                    </p>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setAbierto(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
