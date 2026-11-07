import { router, useForm } from '@inertiajs/react';
import { Fuel, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import adicionales from '@/actions/App/Http/Controllers/AdicionalTramoController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Sentido, Vuelta } from '@/types/vueltas';

const NOMBRE_TRAMO: Record<Sentido, string> = { ida: 'Ida', regreso: 'Vuelta' };

/**
 * Galones extra para un tramo de este ticket, con su motivo: un desvío que
 * no se repite (descargar en Lurín antes de subir). Suben el tope del tramo
 * como una ruta más. Lo que se repite (Huaral) va como complemento del
 * catálogo, al programar.
 */
export function AdicionalesVuelta({ vuelta }: { vuelta: Vuelta }) {
    const [agregando, setAgregando] = useState(false);

    if (vuelta.adicionales.length === 0 && !vuelta.editable) {
        return null;
    }

    const quitar = (id: number) =>
        router.delete(adicionales.destroy([vuelta.id, id]).url, {
            preserveScroll: true,
        });

    return (
        <section className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Combustible adicional
                </h3>
                {vuelta.editable && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setAgregando(true)}
                    >
                        <Plus className="size-3.5" />
                        Adicional
                    </Button>
                )}
            </div>
            {vuelta.adicionales.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Para un desvío que no se repite: galones extra solo para
                    este ticket, con su motivo.
                </p>
            ) : (
                <ul className="flex flex-col divide-y rounded-lg border">
                    {vuelta.adicionales.map((adicional) => (
                        <li
                            key={adicional.id}
                            className="flex items-start justify-between gap-2 p-2 text-sm"
                        >
                            <span className="flex flex-col">
                                <span>
                                    <Fuel className="mr-1 inline size-3.5" />
                                    <span className="font-medium tabular-nums">
                                        +{adicional.galones} gal
                                    </span>{' '}
                                    <span
                                        className={cn(
                                            'text-xs font-medium',
                                            adicional.sentido === 'ida'
                                                ? 'text-amber-700 dark:text-amber-400'
                                                : 'text-violet-700 dark:text-violet-400',
                                        )}
                                    >
                                        {NOMBRE_TRAMO[adicional.sentido]}
                                    </span>
                                </span>
                                <span className="text-xs">
                                    {adicional.motivo}
                                </span>
                                <span className="text-[11px] text-muted-foreground">
                                    {formatearFecha(adicional.fecha)}
                                    {adicional.por && ` · ${adicional.por}`}
                                </span>
                            </span>
                            {adicional.quitable && (
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Quitar adicional"
                                    title="Quitar (el tramo todavía no tiene combustible)"
                                    onClick={() => quitar(adicional.id)}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {agregando && (
                <AdicionalDialog
                    vuelta={vuelta}
                    onCerrar={() => setAgregando(false)}
                />
            )}
        </section>
    );
}

function AdicionalDialog({
    vuelta,
    onCerrar,
}: {
    vuelta: Vuelta;
    onCerrar: () => void;
}) {
    // El tramo por defecto: la vuelta si ya está programada; si no, la ida.
    const { data, setData, post, processing, errors } = useForm<{
        sentido: Sentido;
        galones: string;
        motivo: string;
    }>({
        sentido: vuelta.regreso.length > 0 ? 'regreso' : 'ida',
        galones: '',
        motivo: '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(adicionales.store(vuelta.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="md:max-w-md">
                <DialogHeader>
                    <DialogTitle>Combustible adicional</DialogTitle>
                    <DialogDescription>
                        Galones extra solo para este ticket, por un desvío que
                        no se repite. Sube el tope del tramo. Si se repite
                        seguido, conviene un complemento en el catálogo, como
                        Huaral.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="agregar-adicional"
                    onSubmit={enviar}
                    className="flex flex-col gap-4 px-1"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Tramo" error={errors.sentido} required>
                            {() => (
                                <Select
                                    value={data.sentido}
                                    onValueChange={(valor) =>
                                        setData('sentido', valor as Sentido)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ida">Ida</SelectItem>
                                        <SelectItem value="regreso">
                                            Vuelta
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field label="Galones" error={errors.galones} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    inputMode="decimal"
                                    min="1"
                                    step="1"
                                    autoFocus
                                    value={data.galones}
                                    onChange={(evento) =>
                                        setData('galones', evento.target.value)
                                    }
                                />
                            )}
                        </Field>
                    </div>
                    <Field label="Motivo" error={errors.motivo} required>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                maxLength={180}
                                placeholder="Ej.: descarga en Lurín antes de subir"
                                value={data.motivo}
                                onChange={(evento) =>
                                    setData('motivo', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </form>
                <DialogFooter>
                    <Button
                        type="submit"
                        form="agregar-adicional"
                        disabled={
                            processing ||
                            data.galones === '' ||
                            data.motivo.trim() === ''
                        }
                    >
                        Agregar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
