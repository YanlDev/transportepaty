import { useForm } from '@inertiajs/react';
import { medir } from '@/actions/App/Http/Controllers/LlantaController';
import InputError from '@/components/input-error';
import { formatearMm } from '@/components/llantas/esquema-llantas';
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
import type { PosicionLlanta } from '@/types/llantas';

type Formulario = {
    fecha: string;
    kilometraje: string;
    /** Por código de posición; las que quedan en blanco no se envían. */
    profundidades: Record<string, string>;
};

/**
 * La vuelta con el medidor: la profundidad de todas las llantas puestas, en
 * el orden de las posiciones. Junto a cada una, su medida anterior.
 */
export function MedirLlantasDialog({
    vehiculoId,
    esTracto,
    kmActual,
    hoy,
    posiciones,
    abierto,
    onAbiertoChange,
}: {
    vehiculoId: number;
    esTracto: boolean;
    kmActual: number | null;
    hoy: string;
    posiciones: PosicionLlanta[];
    abierto: boolean;
    onAbiertoChange: (abierto: boolean) => void;
}) {
    const montadas = posiciones.filter((p) => p.llanta !== null);

    const { data, setData, post, transform, processing, errors, reset } =
        useForm<Formulario>({
            fecha: hoy,
            kilometraje: kmActual === null ? '' : String(kmActual),
            profundidades: {},
        });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            fecha: datos.fecha,
            kilometraje:
                esTracto && datos.kilometraje ? datos.kilometraje : null,
            mediciones: montadas
                .filter((p) => (datos.profundidades[p.codigo] ?? '') !== '')
                .map((p) => ({
                    posicion: p.codigo,
                    profundidad_mm: datos.profundidades[p.codigo].replace(
                        ',',
                        '.',
                    ),
                })),
        }));

        post(medir(vehiculoId).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onAbiertoChange(false);
            },
        });
    };

    const errorDe = (codigo: string): string | undefined => {
        const indice = montadas
            .filter((p) => (data.profundidades[p.codigo] ?? '') !== '')
            .findIndex((p) => p.codigo === codigo);

        return indice < 0
            ? undefined
            : errors[
                  `mediciones.${indice}.profundidad_mm` as keyof typeof errors
              ];
    };

    return (
        <Dialog open={abierto} onOpenChange={onAbiertoChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Medir llantas</DialogTitle>
                        <DialogDescription>
                            La profundidad del dibujo, en mm. Las que dejes en
                            blanco no se registran.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="medir-fecha">Fecha</Label>
                            <Input
                                id="medir-fecha"
                                type="date"
                                max={hoy}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                            <InputError message={errors.fecha} />
                        </div>
                        {esTracto && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="medir-km">Km del tracto</Label>
                                <Input
                                    id="medir-km"
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

                    <InputError
                        message={
                            (errors as Record<string, string | undefined>)
                                .mediciones
                        }
                    />

                    <div className="grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-3">
                        {montadas.map((posicion) => (
                            <div key={posicion.codigo} className="grid gap-1">
                                <Label
                                    htmlFor={`mm-${posicion.codigo}`}
                                    className="flex items-baseline justify-between gap-1"
                                >
                                    <span>Posición {posicion.codigo}</span>
                                    <span className="text-[11px] font-normal text-muted-foreground tabular-nums">
                                        antes{' '}
                                        {formatearMm(
                                            posicion.llanta?.profundidad_mm,
                                        )}
                                    </span>
                                </Label>
                                <Input
                                    id={`mm-${posicion.codigo}`}
                                    inputMode="decimal"
                                    placeholder="mm"
                                    value={
                                        data.profundidades[posicion.codigo] ??
                                        ''
                                    }
                                    onChange={(e) =>
                                        setData('profundidades', {
                                            ...data.profundidades,
                                            [posicion.codigo]: e.target.value,
                                        })
                                    }
                                    className="h-11 text-base tabular-nums"
                                />
                                <InputError
                                    message={errorDe(posicion.codigo)}
                                />
                            </div>
                        ))}
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
                            Guardar medición
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
