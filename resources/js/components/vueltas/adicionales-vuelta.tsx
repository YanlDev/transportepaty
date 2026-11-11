import { router, useForm, usePage } from '@inertiajs/react';
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
import type { OpcionesVuelta, Sentido, Vuelta } from '@/types/vueltas';

const NOMBRE_TRAMO: Record<Sentido, string> = { ida: 'Ida', regreso: 'Vuelta' };

/**
 * Lo que se le suma a un tramo del ticket después de programado, con su
 * motivo: una ruta de la tabla (la carga posterior: descarga en Moquegua y
 * carga harina en Ilo) o galones sueltos para un desvío que no está en la
 * tabla (descargar en Lurín antes de subir). Suben el tope del tramo como
 * una ruta más, aunque el tramo ya tenga combustible autorizado.
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
                    Carga posterior y adicionales
                </h3>
                {vuelta.editable && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setAgregando(true)}
                    >
                        <Plus className="size-3.5" />
                        Sumar al tramo
                    </Button>
                )}
            </div>
            {vuelta.adicionales.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Si la unidad carga algo más en el camino o se desvía: suma
                    una ruta de la tabla o galones sueltos, con su motivo.
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
                                {adicional.ruta && (
                                    <span className="text-xs font-medium">
                                        {adicional.ruta}
                                        {adicional.viaticos > 0 &&
                                            ` · viáticos S/ ${adicional.viaticos}`}
                                    </span>
                                )}
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
                    vueltaId={vuelta.id}
                    sentidoInicial={
                        vuelta.regreso.length > 0 ? 'regreso' : 'ida'
                    }
                    onCerrar={() => setAgregando(false)}
                />
            )}
        </section>
    );
}

/**
 * También se abre desde la bandeja de GR, al asignar una carga que no
 * estaba programada: ahí llega con el tramo y el motivo ya puestos.
 */
export function AdicionalDialog({
    vueltaId,
    sentidoInicial,
    motivoInicial = '',
    titulo = 'Sumar al tramo',
    descripcion,
    textoCerrar,
    onCerrar,
}: {
    vueltaId: number;
    /** La vuelta si ya está programada; si no, la ida. */
    sentidoInicial: Sentido;
    motivoInicial?: string;
    titulo?: string;
    descripcion?: string;
    /** Con texto, muestra el botón para cerrar sin sumar nada. */
    textoCerrar?: string;
    onCerrar: () => void;
}) {
    // Las rutas de la tabla llegan con la página (las mismas del formulario
    // de programar); sin ellas solo quedan los galones sueltos.
    const { tramos } = usePage<{ tramos?: OpcionesVuelta['tramos'] }>().props;
    const [tipo, setTipo] = useState<'ruta' | 'libre'>(
        tramos ? 'ruta' : 'libre',
    );

    const { data, setData, transform, post, processing, errors } = useForm<{
        sentido: Sentido;
        ruta_id: string;
        variante_ruta_id: string;
        galones: string;
        motivo: string;
    }>({
        sentido: sentidoInicial,
        ruta_id: '',
        variante_ruta_id: '',
        galones: '',
        motivo: motivoInicial,
    });

    // Lo que se suma en el camino casi siempre es un complemento (Camaná,
    // Ilo, Huaral): van primero, antes de las rutas principales.
    const rutas = [...(tramos?.[data.sentido] ?? [])].sort(
        (una, otra) => Number(otra.es_complemento) - Number(una.es_complemento),
    );
    const elegida = rutas.find((ruta) => ruta.value === data.ruta_id);
    const errores = errors as Record<string, string | undefined>;

    // Viaja solo lo del tipo elegido: una ruta de la tabla o galones.
    transform((datos) =>
        tipo === 'ruta'
            ? {
                  sentido: datos.sentido,
                  ruta_id: datos.ruta_id,
                  variante_ruta_id: datos.variante_ruta_id || null,
                  motivo: datos.motivo,
              }
            : {
                  sentido: datos.sentido,
                  galones: datos.galones,
                  motivo: datos.motivo,
              },
    );

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(adicionales.store(vueltaId).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    const incompleto =
        data.motivo.trim() === '' ||
        (tipo === 'ruta' ? data.ruta_id === '' : data.galones === '');

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="md:max-w-md">
                <DialogHeader>
                    <DialogTitle>{titulo}</DialogTitle>
                    <DialogDescription>
                        {descripcion ??
                            'Para la carga que sale en el camino o un desvío. Sube el tope del tramo, aunque ya tenga combustible autorizado.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="agregar-adicional"
                    onSubmit={enviar}
                    className="flex flex-col gap-4 px-1"
                >
                    {tramos && (
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    tipo === 'ruta' ? 'default' : 'outline'
                                }
                                onClick={() => setTipo('ruta')}
                            >
                                Ruta de la tabla
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    tipo === 'libre' ? 'default' : 'outline'
                                }
                                onClick={() => setTipo('libre')}
                            >
                                Galones sueltos
                            </Button>
                        </div>
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Tramo" error={errors.sentido} required>
                            {() => (
                                <Select
                                    value={data.sentido}
                                    onValueChange={(valor) =>
                                        setData((anterior) => ({
                                            ...anterior,
                                            sentido: valor as Sentido,
                                            ruta_id: '',
                                            variante_ruta_id: '',
                                        }))
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
                        {tipo === 'libre' && (
                            <Field
                                label="Galones"
                                error={errors.galones}
                                required
                            >
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
                                            setData(
                                                'galones',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        )}
                    </div>
                    {tipo === 'ruta' && (
                        <Field
                            label="Ruta"
                            error={errores.ruta_id}
                            required
                            ayuda={elegida?.dotacion || undefined}
                        >
                            {() => (
                                <Select
                                    value={data.ruta_id || undefined}
                                    onValueChange={(valor) =>
                                        setData((anterior) => ({
                                            ...anterior,
                                            ruta_id: valor,
                                            variante_ruta_id: '',
                                        }))
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Elige la ruta que se suma" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {rutas.map((ruta) => (
                                            <SelectItem
                                                key={ruta.value}
                                                value={ruta.value}
                                            >
                                                {ruta.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    )}
                    {tipo === 'ruta' &&
                        elegida &&
                        elegida.variantes.length > 0 && (
                            <Field
                                label="Variante (según el peso)"
                                error={errores.variante_ruta_id}
                                ayuda="Si todavía no se sabe, se elige después en Abastecimiento."
                            >
                                {() => (
                                    <Select
                                        value={
                                            data.variante_ruta_id || undefined
                                        }
                                        onValueChange={(valor) =>
                                            setData('variante_ruta_id', valor)
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Por definir" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {elegida.variantes.map(
                                                (variante) => (
                                                    <SelectItem
                                                        key={variante.id}
                                                        value={String(
                                                            variante.id,
                                                        )}
                                                    >
                                                        {variante.nombre}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                        )}
                    <Field label="Motivo" error={errors.motivo} required>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                maxLength={180}
                                placeholder={
                                    tipo === 'ruta'
                                        ? 'Ej.: descargó en Moquegua y carga harina en Ilo'
                                        : 'Ej.: descarga en Lurín antes de subir'
                                }
                                value={data.motivo}
                                onChange={(evento) =>
                                    setData('motivo', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </form>
                <DialogFooter>
                    {textoCerrar && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCerrar}
                        >
                            {textoCerrar}
                        </Button>
                    )}
                    <Button
                        type="submit"
                        form="agregar-adicional"
                        disabled={processing || incompleto}
                    >
                        Agregar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
