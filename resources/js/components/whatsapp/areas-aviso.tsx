import { router, useForm } from '@inertiajs/react';
import { Plus, Trash } from '@phosphor-icons/react';
import areasAviso from '@/actions/App/Http/Controllers/AreaAvisoController';
import whatsapp from '@/actions/App/Http/Controllers/WhatsappController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type AreaAviso = {
    id: number;
    nombre: string;
    numero: string;
    ve_flete: boolean;
    activa: boolean;
};

type DatosArea = Omit<AreaAviso, 'id'>;

/**
 * Las áreas que reciben el aviso de cada salida en Programación. Todas ven
 * la unidad, el conductor, el cliente, dónde carga y a dónde va; «ve el
 * flete» suma el precio acordado, que solo le toca a quien factura.
 */
export function AreasAviso({ areas }: { areas: AreaAviso[] }) {
    return (
        <section className="flex max-w-2xl flex-col gap-4 rounded-xl border bg-card p-5">
            <div>
                <h2 className="font-semibold">Áreas que reciben avisos</h2>
                <p className="text-sm text-muted-foreground">
                    Cada una tiene su botón en Programación. Las desactivadas no
                    aparecen.
                </p>
            </div>

            <div className="flex flex-col divide-y">
                {areas.map((area) => (
                    <FilaArea key={area.id} area={area} />
                ))}
                <FilaArea />
            </div>
        </section>
    );
}

/** Una fila editable; sin `area`, es la fila para agregar una nueva. */
function FilaArea({ area }: { area?: AreaAviso }) {
    const nueva = area === undefined;
    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        isDirty,
        setDefaults,
    } = useForm<DatosArea>({
        nombre: area?.nombre ?? '',
        numero: area?.numero ?? '',
        ve_flete: area?.ve_flete ?? false,
        activa: area?.activa ?? true,
    });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        if (nueva) {
            post(areasAviso.store().url, {
                preserveScroll: true,
                onSuccess: () => reset(),
            });

            return;
        }

        put(areasAviso.update(area.id).url, {
            preserveScroll: true,
            onSuccess: () => setDefaults(),
        });
    };

    const quitar = () => {
        if (
            area &&
            window.confirm(`¿Quitar ${area.nombre}? Dejará de recibir avisos.`)
        ) {
            router.delete(areasAviso.destroy(area.id).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <form
            onSubmit={guardar}
            className={cn(
                'flex flex-col gap-2 py-3',
                !nueva && !data.activa && 'opacity-60',
            )}
        >
            <div className="flex flex-wrap items-start gap-2">
                <div className="flex min-w-40 flex-1 flex-col gap-1">
                    <Input
                        value={data.nombre}
                        onChange={(evento) =>
                            setData('nombre', evento.target.value)
                        }
                        placeholder={
                            nueva
                                ? 'Nueva área, ej. Centro de Control'
                                : 'Nombre'
                        }
                        aria-label="Nombre del área"
                    />
                    <InputError message={errors.nombre} />
                </div>
                <div className="flex w-40 flex-col gap-1">
                    <Input
                        value={data.numero}
                        onChange={(evento) =>
                            setData('numero', evento.target.value)
                        }
                        placeholder="Celular"
                        inputMode="tel"
                        className="font-mono"
                        aria-label="Número de WhatsApp del área"
                    />
                    <InputError message={errors.numero} />
                </div>

                <Button
                    type="submit"
                    variant={nueva ? 'default' : 'outline'}
                    disabled={
                        processing || !isDirty || !data.nombre || !data.numero
                    }
                >
                    {processing ? (
                        <Spinner />
                    ) : (
                        nueva && <Plus className="size-4" />
                    )}
                    {nueva ? 'Agregar' : 'Guardar'}
                </Button>

                {!nueva && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={quitar}
                        aria-label={`Quitar ${area.nombre}`}
                        className="text-muted-foreground hover:text-destructive"
                    >
                        <Trash className="size-4" />
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap gap-5 text-sm">
                <Label className="flex items-center gap-2 font-normal">
                    <Checkbox
                        checked={data.ve_flete}
                        onCheckedChange={(valor) =>
                            setData('ve_flete', valor === true)
                        }
                    />
                    Ve el flete
                </Label>
                {!nueva && (
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={data.activa}
                            onCheckedChange={(valor) =>
                                setData('activa', valor === true)
                            }
                        />
                        Activa
                    </Label>
                )}
            </div>
        </form>
    );
}

/** El teléfono que va al pie de la advertencia de documentación. */
export function TelefonoOficina({ telefono }: { telefono: string | null }) {
    const { data, setData, put, processing, errors, isDirty, setDefaults } =
        useForm({
            telefono_oficina: telefono ?? '',
        });

    return (
        <section className="flex max-w-2xl flex-col gap-3 rounded-xl border bg-card p-5">
            <div>
                <h2 className="font-semibold">Teléfono de la oficina</h2>
                <p className="text-sm text-muted-foreground">
                    Va al pie de la advertencia, para que el conductor sepa a
                    quién llamar.
                </p>
            </div>
            <form
                className="flex flex-wrap gap-2"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    put(whatsapp.actualizarOficina().url, {
                        preserveScroll: true,
                        onSuccess: () => setDefaults(),
                    });
                }}
            >
                <Input
                    value={data.telefono_oficina}
                    onChange={(evento) =>
                        setData('telefono_oficina', evento.target.value)
                    }
                    placeholder="Ej. 923-275-353 / 980-586-089"
                    aria-label="Teléfono de la oficina"
                    className="max-w-sm"
                />
                <Button
                    type="submit"
                    variant="outline"
                    disabled={processing || !isDirty}
                >
                    {processing && <Spinner />}
                    Guardar
                </Button>
                <InputError message={errors.telefono_oficina} />
            </form>
        </section>
    );
}
