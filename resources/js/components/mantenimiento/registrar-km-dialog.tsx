import { useForm } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/LecturaKilometrajeController';
import { CampoArchivo } from '@/components/campo-archivo';
import InputError from '@/components/input-error';
import { formatearKm } from '@/components/mantenimiento/estado-mantenimiento';
import { SelectorBuscable } from '@/components/selector-buscable';
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
import { formatearPlaca } from '@/lib/format';
import type { TractoKm } from '@/types/mantenimiento';

type Formulario = {
    vehiculo_id: number | null;
    kilometraje: string;
    foto: File | null;
    observacion: string;
};

type Props = {
    tractos: TractoKm[];
    /** La unidad ya elegida, cuando se abre desde su ficha. */
    vehiculoInicial?: number;
    /** Disparador propio; sin él, el botón «Registrar km». */
    trigger?: React.ReactNode;
    /** Para abrirlo desde afuera, como el botón flotante del celular. */
    abierto?: boolean;
    onAbiertoChange?: (abierto: boolean) => void;
};

/**
 * Anotar el km de una unidad al llegar a base. Pensado para el celular: la
 * placa se busca escribiendo, el teclado que sale es el numérico y la foto
 * del tablero abre directo la cámara de atrás.
 *
 * Mientras se escribe, muestra cuánto recorrió desde el último registro: un
 * número absurdo (de más o de menos) salta a la vista antes de guardarlo.
 */
export function RegistrarKmDialog({
    tractos,
    vehiculoInicial,
    trigger,
    abierto: abiertoControlado,
    onAbiertoChange,
}: Props) {
    const [abiertoInterno, setAbiertoInterno] = useState(false);
    const abierto = abiertoControlado ?? abiertoInterno;
    const [comprimiendo, setComprimiendo] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        progress,
        errors,
        reset,
        clearErrors,
    } = useForm<Formulario>({
        vehiculo_id: vehiculoInicial ?? null,
        kilometraje: '',
        foto: null,
        observacion: '',
    });

    const tracto = tractos.find((opcion) => opcion.id === data.vehiculo_id);
    const nuevo = Number(data.kilometraje);
    const recorrido =
        tracto?.km_actual != null && data.kilometraje !== ''
            ? nuevo - tracto.km_actual
            : null;

    const cambiarAbierto = (valor: boolean) => {
        if (abiertoControlado === undefined) {
            setAbiertoInterno(valor);
        }

        onAbiertoChange?.(valor);

        if (!valor) {
            clearErrors();
        }
    };

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setData('vehiculo_id', vehiculoInicial ?? null);
                cambiarAbierto(false);
            },
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={cambiarAbierto}>
            {(trigger !== undefined || abiertoControlado === undefined) && (
                <DialogTrigger asChild>
                    {trigger ?? (
                        <Button>
                            <Gauge className="size-4" />
                            Registrar km
                        </Button>
                    )}
                </DialogTrigger>
            )}
            <DialogContent>
                <form onSubmit={guardar}>
                    <DialogHeader>
                        <DialogTitle>Registrar km</DialogTitle>
                        <DialogDescription>
                            El kilometraje del tablero al llegar a base.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="km-unidad">Unidad</Label>
                            <SelectorBuscable
                                id="km-unidad"
                                valor={data.vehiculo_id}
                                onCambio={(valor) =>
                                    setData('vehiculo_id', valor)
                                }
                                etiqueta="Placa"
                                opciones={tractos.map((opcion) => ({
                                    valor: opcion.id,
                                    etiqueta: formatearPlaca(opcion.placa),
                                    detalle: `${opcion.placa} ${opcion.marca ?? ''}`,
                                }))}
                                invalido={!!errors.vehiculo_id}
                            />
                            {tracto && (
                                <p className="text-xs text-muted-foreground">
                                    {tracto.marca} · último registro:{' '}
                                    {formatearKm(tracto.km_actual)}
                                </p>
                            )}
                            <InputError message={errors.vehiculo_id} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="km-valor">Kilometraje</Label>
                            <Input
                                id="km-valor"
                                inputMode="numeric"
                                pattern="[0-9]*"
                                autoComplete="off"
                                className="h-12 text-lg tabular-nums"
                                placeholder="Ej. 315420"
                                value={data.kilometraje}
                                onChange={(evento) =>
                                    setData(
                                        'kilometraje',
                                        evento.target.value.replace(/\D/g, ''),
                                    )
                                }
                            />
                            {recorrido !== null && (
                                <p
                                    className={
                                        recorrido < 0 || recorrido > 5000
                                            ? 'text-xs font-medium text-amber-700 dark:text-amber-400'
                                            : 'text-xs text-muted-foreground'
                                    }
                                >
                                    {recorrido < 0
                                        ? `Es ${formatearKm(Math.abs(recorrido))} menos que el último registro: revisa el número o la placa.`
                                        : `Recorrió ${formatearKm(recorrido)} desde el último registro.`}
                                </p>
                            )}
                            <InputError message={errors.kilometraje} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Foto del tablero (opcional)</Label>
                            <CampoArchivo
                                acepta="imagen"
                                archivos={data.foto ? [data.foto] : []}
                                onArchivos={(lista) =>
                                    setData('foto', lista[0] ?? null)
                                }
                                progreso={progress?.percentage ?? null}
                                error={errors.foto}
                                onPreparando={setComprimiendo}
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="km-observacion">
                                Observación (opcional)
                            </Label>
                            <Input
                                id="km-observacion"
                                value={data.observacion}
                                onChange={(evento) =>
                                    setData('observacion', evento.target.value)
                                }
                                placeholder="Ej. llegó con luz de aceite"
                            />
                            <InputError message={errors.observacion} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            className="h-12 w-full sm:h-9 sm:w-auto"
                            disabled={
                                processing ||
                                comprimiendo ||
                                data.vehiculo_id === null ||
                                data.kilometraje === ''
                            }
                        >
                            {processing && <Spinner />}
                            Guardar km
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
