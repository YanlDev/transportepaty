import { useForm } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useId, useState } from 'react';
import { updateComponente } from '@/actions/App/Http/Controllers/ParametroCostoController';
import InputError from '@/components/input-error';
import { EntradasEditor } from '@/components/parametros-costo/entradas-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ComponenteCosto,
    EntradasComponente,
    EnumOption,
    PasoDerivacion,
} from '@/types/fleet';

type FormData = {
    nombre: string;
    naturaleza: string;
    activo: boolean;
    entradas: EntradasComponente;
};

export function ComponenteCard({
    componente,
    naturalezas,
}: {
    componente: ComponenteCosto;
    naturalezas: EnumOption[];
}) {
    const [abierto, setAbierto] = useState(false);
    const nombreId = useId();
    const naturalezaId = useId();

    const { data, setData, put, processing, errors, isDirty } =
        useForm<FormData>({
            nombre: componente.nombre,
            naturaleza: componente.naturaleza,
            activo: componente.activo,
            entradas: componente.entradas,
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
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
                        {componente.metodo_label}
                    </p>
                </div>

                <Badge
                    variant={
                        componente.naturaleza === 'directo'
                            ? 'default'
                            : 'secondary'
                    }
                >
                    {componente.naturaleza === 'directo'
                        ? 'Directo'
                        : 'Indirecto'}
                </Badge>

                <div className="w-32 shrink-0 text-right">
                    <p className="font-mono text-sm text-foreground tabular-nums">
                        {formatearSoles(componente.tasa)}
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
                    {componente.pasos.length > 0 && (
                        <Derivacion
                            pasos={componente.pasos}
                            tasa={componente.tasa}
                            unidad={componente.unidad}
                            nombre={componente.nombre}
                        />
                    )}

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
                            <Label htmlFor={naturalezaId}>Naturaleza</Label>
                            <Select
                                value={data.naturaleza}
                                onValueChange={(valor) =>
                                    setData('naturaleza', valor)
                                }
                            >
                                <SelectTrigger id={naturalezaId}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {naturalezas.map((opcion) => (
                                        <SelectItem
                                            key={opcion.value}
                                            value={opcion.value}
                                        >
                                            {opcion.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.naturaleza} />
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

                    <EntradasEditor
                        metodo={componente.metodo}
                        entradas={data.entradas}
                        onChange={(entradas) => setData('entradas', entradas)}
                        errors={errors as Record<string, string>}
                    />

                    <div className="flex items-center justify-end gap-3 border-t border-border pt-3">
                        {isDirty && (
                            <p className="text-xs text-muted-foreground">
                                La tasa se recalcula al guardar.
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
                        {formatearSoles(tasa)}{' '}
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
