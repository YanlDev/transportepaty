import { useForm } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/LlantaController';
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
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type { PosicionLlanta } from '@/types/llantas';

/**
 * Corregir lo que se escribió mal al instalar la llanta. La fecha y el km
 * de instalación se corrigen en su movimiento del historial.
 */
export function EditarLlantaDialog({
    posicion,
    condiciones,
    abierto,
    onAbiertoChange,
}: {
    posicion: PosicionLlanta & {
        llanta: NonNullable<PosicionLlanta['llanta']>;
    };
    condiciones: EnumOption[];
    abierto: boolean;
    onAbiertoChange: (abierto: boolean) => void;
}) {
    const llanta = posicion.llanta;

    const { data, setData, put, processing, errors } = useForm({
        marca: llanta.marca ?? '',
        medida: llanta.medida ?? '',
        codigo: llanta.codigo ?? '',
        condicion: llanta.condicion,
    });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        put(update(llanta.id).url, {
            preserveScroll: true,
            onSuccess: () => onAbiertoChange(false),
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={onAbiertoChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Datos de la llanta</DialogTitle>
                        <DialogDescription>
                            Posición {posicion.codigo} · {posicion.nombre}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5">
                        <Label>La llanta es</Label>
                        <div className="grid grid-cols-3 gap-2">
                            {condiciones.map((opcion) => (
                                <button
                                    key={opcion.value}
                                    type="button"
                                    onClick={() =>
                                        setData('condicion', opcion.value)
                                    }
                                    aria-pressed={
                                        data.condicion === opcion.value
                                    }
                                    className={cn(
                                        'min-h-11 rounded-lg border px-2 text-sm font-medium transition-colors',
                                        data.condicion === opcion.value
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {opcion.label}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.condicion} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="editar-marca">Marca</Label>
                            <Input
                                id="editar-marca"
                                value={data.marca}
                                onChange={(e) =>
                                    setData('marca', e.target.value)
                                }
                            />
                            <InputError message={errors.marca} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="editar-medida">Medida</Label>
                            <Input
                                id="editar-medida"
                                value={data.medida}
                                onChange={(e) =>
                                    setData('medida', e.target.value)
                                }
                            />
                            <InputError message={errors.medida} />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="editar-codigo">Código o serie</Label>
                        <Input
                            id="editar-codigo"
                            value={data.codigo}
                            onChange={(e) => setData('codigo', e.target.value)}
                        />
                        <InputError message={errors.codigo} />
                    </div>

                    <p className="text-xs text-muted-foreground">
                        La fecha y el km de instalación se corrigen desde su
                        movimiento en el historial.
                    </p>

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
