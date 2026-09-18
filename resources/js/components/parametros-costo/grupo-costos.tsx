import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useId, useState } from 'react';
import { storeComponente } from '@/actions/App/Http/Controllers/ParametroCostoController';
import InputError from '@/components/input-error';
import {
    ComponenteCard,
    formatearTasa,
} from '@/components/parametros-costo/componente-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { ComponenteCosto } from '@/types/fleet';

export function GrupoCostos({
    titulo,
    descripcion,
    tipo,
    componentes,
}: {
    titulo: string;
    descripcion: string;
    tipo: 'fijo_dia' | 'variable_km';
    componentes: ComponenteCosto[];
}) {
    return (
        <section className="flex flex-col gap-3">
            <div>
                <h2 className="text-sm font-semibold text-foreground">
                    {titulo}
                </h2>
                <p className="text-xs text-muted-foreground">{descripcion}</p>
            </div>

            {componentes.map((componente) => (
                <ComponenteCard key={componente.id} componente={componente} />
            ))}

            <NuevaLinea tipo={tipo} />
        </section>
    );
}

/**
 * Una línea nueva entra con su tasa escrita y sin calculadora: es un número
 * que ya se usa (una escolta, un seguro adicional).
 */
function NuevaLinea({ tipo }: { tipo: 'fijo_dia' | 'variable_km' }) {
    const [abierto, setAbierto] = useState(false);
    const nombreId = useId();
    const tasaId = useId();

    const { data, setData, post, processing, errors, reset } = useForm({
        nombre: '',
        tipo,
        tasa: '',
    });

    if (!abierto) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={() => setAbierto(true)}
            >
                <Plus className="size-4" />
                Agregar línea
            </Button>
        );
    }

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(storeComponente().url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border border-dashed border-border p-4 sm:grid-cols-[1fr_12rem_auto] sm:items-end"
        >
            <div className="grid gap-1.5">
                <Label htmlFor={nombreId}>Nombre</Label>
                <Input
                    id={nombreId}
                    value={data.nombre}
                    onChange={(e) => setData('nombre', e.target.value)}
                    placeholder="Escolta"
                    autoFocus
                />
                <InputError message={errors.nombre} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor={tasaId}>
                    Tasa ({tipo === 'fijo_dia' ? 'S/ por día' : 'S/ por km'})
                </Label>
                <Input
                    id={tasaId}
                    type="number"
                    inputMode="decimal"
                    step="0.0001"
                    min={0}
                    value={data.tasa}
                    onChange={(e) => setData('tasa', e.target.value)}
                    className="font-mono tabular-nums"
                />
                <InputError message={errors.tasa} />
            </div>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => setAbierto(false)}
                >
                    Cancelar
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Agregar
                </Button>
            </div>
        </form>
    );
}

export function ResumenCosto({
    titulo,
    unidad,
    total,
}: {
    titulo: string;
    unidad: string;
    total: number;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <p className="text-xs text-muted-foreground">
                {titulo} {unidad}
            </p>
            <p className="font-mono text-2xl font-semibold text-foreground tabular-nums">
                {formatearTasa(total)}
            </p>
        </div>
    );
}
