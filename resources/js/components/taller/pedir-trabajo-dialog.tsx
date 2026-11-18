import { useForm } from '@inertiajs/react';
import { Hammer } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/SolicitudTrabajoController';
import { CampoArchivo } from '@/components/campo-archivo';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';

type Formulario = {
    vehiculo_id: number | null;
    trabajo: string;
    prioridad: 'normal' | 'urgente';
    fotos: File[];
};

/**
 * Pedirle un trabajo a taller: la unidad, qué le pasa y qué tan urgente es,
 * con fotos si las hay. Queda como solicitud hasta que taller la acepta y le
 * asigna mecánico; mientras tanto no bloquea a la unidad.
 */
export function PedirTrabajoDialog({
    unidades,
}: {
    unidades: { id: number; placa: string; tipo: string }[];
}) {
    const [abierto, setAbierto] = useState(false);
    const [preparando, setPreparando] = useState(false);
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
        vehiculo_id: null,
        trabajo: '',
        prioridad: 'normal',
        fotos: [],
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();

        post(store().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(valor) => {
                setAbierto(valor);

                if (!valor) {
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" className="h-11">
                    <Hammer className="size-4" />
                    Pedir trabajo a taller
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Pedir un trabajo a taller</DialogTitle>
                        <DialogDescription>
                            Taller lo acepta y le asigna mecánico. Verás en qué
                            va desde esta misma pantalla.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5">
                        <Label htmlFor="pedido-unidad">Unidad</Label>
                        <SelectorBuscable
                            id="pedido-unidad"
                            valor={data.vehiculo_id}
                            onCambio={(valor) => setData('vehiculo_id', valor)}
                            etiqueta="Placa"
                            opciones={unidades.map((unidad) => ({
                                valor: unidad.id,
                                etiqueta: formatearPlaca(unidad.placa),
                                detalle: `${unidad.tipo} ${unidad.placa}`,
                            }))}
                            invalido={!!errors.vehiculo_id}
                        />
                        <InputError message={errors.vehiculo_id} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="pedido-trabajo">
                            ¿Qué le pasa o qué hay que hacerle?
                        </Label>
                        <Textarea
                            id="pedido-trabajo"
                            rows={3}
                            value={data.trabajo}
                            onChange={(evento) =>
                                setData('trabajo', evento.target.value)
                            }
                            placeholder="Fuga de aire en el eje 3, luz de freno quemada…"
                        />
                        <InputError message={errors.trabajo} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label>Prioridad</Label>
                        <div className="grid grid-cols-2 gap-2">
                            {(['normal', 'urgente'] as const).map((opcion) => (
                                <button
                                    key={opcion}
                                    type="button"
                                    aria-pressed={data.prioridad === opcion}
                                    onClick={() => setData('prioridad', opcion)}
                                    className={cn(
                                        'h-11 rounded-lg border text-sm font-medium transition-colors',
                                        data.prioridad === opcion
                                            ? opcion === 'urgente'
                                                ? 'border-red-600 bg-red-600 text-white'
                                                : 'border-primary bg-primary text-primary-foreground'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {opcion === 'urgente'
                                        ? 'Urgente'
                                        : 'Normal'}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.prioridad} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label>Fotos (opcional)</Label>
                        <CampoArchivo
                            acepta="imagen"
                            multiple
                            archivos={data.fotos}
                            onArchivos={(fotos) => setData('fotos', fotos)}
                            progreso={progress?.percentage ?? null}
                            error={
                                errors.fotos ??
                                Object.entries(errors).find(([clave]) =>
                                    clave.startsWith('fotos.'),
                                )?.[1]
                            }
                            onPreparando={setPreparando}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            className="h-12"
                            disabled={processing || preparando}
                        >
                            {processing && <Spinner />}
                            Enviar a taller
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
