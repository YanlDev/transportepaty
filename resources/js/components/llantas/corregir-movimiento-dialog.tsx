import { useForm } from '@inertiajs/react';
import { corregirMovimiento } from '@/actions/App/Http/Controllers/LlantaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { MovimientoLlanta } from '@/types/llantas';

/**
 * Lo que se anotó mal en un movimiento: la fecha, el km, los mm o la
 * observación. Qué llanta y en qué posición no se corrige: eso es otro
 * movimiento.
 */
export function CorregirMovimientoDialog({
    movimiento,
    esTracto,
    hoy,
    abierto,
    onAbiertoChange,
}: {
    movimiento: MovimientoLlanta;
    esTracto: boolean;
    hoy: string;
    abierto: boolean;
    onAbiertoChange: (abierto: boolean) => void;
}) {
    const llevaMm = movimiento.tipo !== 'rotacion';

    const { data, setData, put, transform, processing, errors } = useForm({
        fecha: movimiento.fecha,
        kilometraje:
            movimiento.kilometraje === null
                ? ''
                : String(movimiento.kilometraje),
        profundidad_mm:
            movimiento.profundidad_mm === null
                ? ''
                : String(movimiento.profundidad_mm),
        observacion: movimiento.observacion ?? '',
    });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            fecha: datos.fecha,
            kilometraje:
                esTracto && datos.kilometraje ? datos.kilometraje : null,
            observacion: datos.observacion || null,
            ...(llevaMm && {
                profundidad_mm: datos.profundidad_mm
                    ? datos.profundidad_mm.replace(',', '.')
                    : null,
            }),
        }));

        put(corregirMovimiento(movimiento.id).url, {
            preserveScroll: true,
            onSuccess: () => onAbiertoChange(false),
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={onAbiertoChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Corregir {movimiento.tipo_label.toLowerCase()}
                        </DialogTitle>
                        <DialogDescription>
                            Posición {movimiento.posicion}
                            {movimiento.posicion_destino &&
                                ` → ${movimiento.posicion_destino}`}
                            {movimiento.llanta && ` · ${movimiento.llanta}`}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="corregir-fecha">Fecha</Label>
                            <Input
                                id="corregir-fecha"
                                type="date"
                                max={hoy}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                            <InputError message={errors.fecha} />
                        </div>
                        {llevaMm && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="corregir-mm">Dibujo (mm)</Label>
                                <Input
                                    id="corregir-mm"
                                    inputMode="decimal"
                                    value={data.profundidad_mm}
                                    onChange={(e) =>
                                        setData(
                                            'profundidad_mm',
                                            e.target.value,
                                        )
                                    }
                                    className="tabular-nums"
                                />
                                <InputError message={errors.profundidad_mm} />
                            </div>
                        )}
                        {esTracto && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="corregir-km">
                                    Km del tracto
                                </Label>
                                <Input
                                    id="corregir-km"
                                    inputMode="numeric"
                                    value={data.kilometraje}
                                    onChange={(e) =>
                                        setData(
                                            'kilometraje',
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                    className="tabular-nums"
                                />
                                <InputError message={errors.kilometraje} />
                            </div>
                        )}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="corregir-observacion">
                            Observación
                        </Label>
                        <Textarea
                            id="corregir-observacion"
                            rows={2}
                            value={data.observacion}
                            onChange={(e) =>
                                setData('observacion', e.target.value)
                            }
                        />
                        <InputError message={errors.observacion} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onAbiertoChange(false)}
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
