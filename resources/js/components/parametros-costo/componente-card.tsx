import { useForm } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useId, useState } from 'react';
import { updateComponente } from '@/actions/App/Http/Controllers/ParametroCostoController';
import InputError from '@/components/input-error';
import { EntradasEditor } from '@/components/parametros-costo/entradas-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ComponenteCosto,
    EntradasComponente,
    PasoDerivacion,
} from '@/types/fleet';

type FormData = {
    nombre: string;
    tasa: string;
    activo: boolean;
    entradas: EntradasComponente;
};

/**
 * Una línea del tarifario. Cerrada muestra la tasa con la que se cotiza;
 * abierta deja cambiarla y, si la línea tiene una cuenta detrás, muestra la
 * calculadora de apoyo con lo que sugiere.
 */
export function ComponenteCard({
    componente,
}: {
    componente: ComponenteCosto;
}) {
    const [abierto, setAbierto] = useState(false);
    const nombreId = useId();
    const tasaId = useId();

    const { data, setData, put, transform, processing, errors, isDirty } =
        useForm<FormData>({
            nombre: componente.nombre,
            tasa: componente.tasa.toString(),
            activo: componente.activo,
            entradas: componente.entradas,
        });

    // Lo que sugiere la calculadora sale de las entradas guardadas: mientras
    // haya cambios sin guardar, ese número ya no es el de la pantalla.
    const entradasCambiadas =
        JSON.stringify(data.entradas) !== JSON.stringify(componente.entradas);
    const tasaCalculada = Number(componente.tasa_calculada.toFixed(4));
    const usaLaCalculada = Number(data.tasa) === tasaCalculada;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Una línea sin calculadora no tiene entradas que validar.
        transform(({ entradas, ...datos }) =>
            componente.tiene_calculadora ? { ...datos, entradas } : datos,
        );

        put(updateComponente(componente.id).url, { preserveScroll: true });
    };

    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-card',
                !componente.activo && 'opacity-60',
            )}
        >
            <button
                type="button"
                onClick={() => setAbierto((valor) => !valor)}
                className="flex w-full items-center gap-3 p-4 text-left"
                aria-expanded={abierto}
            >
                <ChevronRight
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform',
                        abierto && 'rotate-90',
                    )}
                />

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground">
                        {componente.nombre}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {!componente.activo
                            ? 'No se cuenta al cotizar'
                            : componente.tiene_calculadora
                              ? `Con calculadora: ${componente.metodo_label.toLowerCase()}`
                              : 'Tasa escrita'}
                    </p>
                </div>

                <div className="w-32 shrink-0 text-right">
                    <p className="font-mono text-sm text-foreground tabular-nums">
                        {formatearTasa(componente.tasa)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {componente.unidad}
                    </p>
                </div>
            </button>

            {abierto && (
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-5 border-t border-border p-4"
                >
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor={nombreId}>Nombre</Label>
                            <Input
                                id={nombreId}
                                value={data.nombre}
                                onChange={(e) =>
                                    setData('nombre', e.target.value)
                                }
                            />
                            <InputError message={errors.nombre} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor={tasaId}>
                                Tasa ({componente.unidad})
                            </Label>
                            <Input
                                id={tasaId}
                                type="number"
                                inputMode="decimal"
                                step="0.0001"
                                min={0}
                                value={data.tasa}
                                onChange={(e) =>
                                    setData('tasa', e.target.value)
                                }
                                className="font-mono tabular-nums"
                            />
                            <InputError message={errors.tasa} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Vigente</Label>
                            <label className="flex h-9 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="size-4 rounded border-input"
                                    checked={data.activo}
                                    onChange={(e) =>
                                        setData('activo', e.target.checked)
                                    }
                                />
                                <span className="text-muted-foreground">
                                    Se cuenta al cotizar
                                </span>
                            </label>
                            <InputError message={errors.activo} />
                        </div>
                    </div>

                    {componente.tiene_calculadora && (
                        <div className="flex flex-col gap-4 rounded-lg border border-dashed border-border p-4">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-xs font-semibold text-foreground">
                                        Calculadora de apoyo
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {entradasCambiadas
                                            ? 'Guardá para ver cuánto sugiere con estos datos.'
                                            : `Sugiere ${formatearTasa(componente.tasa_calculada)} ${componente.unidad.replace('S/ ', '')}.`}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        entradasCambiadas || usaLaCalculada
                                    }
                                    onClick={() =>
                                        setData(
                                            'tasa',
                                            tasaCalculada.toString(),
                                        )
                                    }
                                >
                                    {usaLaCalculada
                                        ? 'Ya usa esta tasa'
                                        : 'Usar esta tasa'}
                                </Button>
                            </div>

                            {componente.pasos.length > 0 && (
                                <Derivacion
                                    pasos={componente.pasos}
                                    tasa={componente.tasa_calculada}
                                    unidad={componente.unidad}
                                    nombre="Tasa sugerida"
                                />
                            )}

                            <EntradasEditor
                                metodo={componente.metodo}
                                entradas={data.entradas}
                                onChange={(entradas) =>
                                    setData('entradas', entradas)
                                }
                                errors={errors as Record<string, string>}
                            />
                        </div>
                    )}

                    <div className="flex items-center justify-end gap-3 border-t border-border pt-3">
                        {isDirty && (
                            <p className="text-xs text-muted-foreground">
                                Las cotizaciones ya emitidas no cambian.
                            </p>
                        )}
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}

/** Las tasas por km tienen centésimos de sol: dos decimales no alcanzan. */
export function formatearTasa(tasa: number): string {
    return `S/ ${tasa.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    })}`;
}

/**
 * El camino que lleva de las entradas a la tasa. Es la respuesta a «¿por qué
 * el día de conductor cuesta esto?», que es la pregunta que aparece cada vez
 * que se discute una tarifa.
 */
function Derivacion({
    pasos,
    tasa,
    unidad,
    nombre,
}: {
    pasos: PasoDerivacion[];
    tasa: number;
    unidad: string;
    nombre: string;
}) {
    return (
        <div className="rounded-lg bg-muted/50 p-4">
            <p className="mb-2 text-xs font-semibold text-foreground">
                Cómo se calcula
            </p>

            <dl className="flex flex-col gap-1">
                {pasos.map((paso) => (
                    <div
                        key={paso.etiqueta}
                        className="flex items-baseline justify-between gap-4 text-xs"
                    >
                        <dt className="text-muted-foreground">
                            {paso.etiqueta}
                        </dt>
                        <dd className="font-mono text-foreground tabular-nums">
                            {formatearPaso(paso)}
                        </dd>
                    </div>
                ))}

                <div className="mt-1 flex items-baseline justify-between gap-4 border-t border-border pt-2 text-xs">
                    <dt className="font-medium text-foreground">{nombre}</dt>
                    <dd className="font-mono font-semibold text-foreground tabular-nums">
                        {formatearTasa(tasa)}{' '}
                        <span className="font-sans font-normal text-muted-foreground">
                            {unidad}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    );
}

function formatearPaso(paso: PasoDerivacion): string {
    switch (paso.formato) {
        case 'porcentaje':
            return `${(paso.valor * 100).toFixed(2)} %`;
        case 'dias':
            return `${paso.valor.toFixed(2)} días`;
        case 'km':
            return `${paso.valor.toLocaleString('es-PE')} km`;
        case 'numero':
            return paso.valor.toLocaleString('es-PE', {
                maximumFractionDigits: 4,
            });
        default:
            return formatearSoles(paso.valor);
    }
}
