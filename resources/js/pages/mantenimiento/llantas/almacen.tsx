import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Boxes, Tag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    ficha,
    store,
} from '@/actions/App/Http/Controllers/LlantaAlmacenController';
import llantasRutas from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { useConfirmar } from '@/components/confirmar';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { BuscarLlanta } from '@/components/llantas/buscar-llanta';
import { SituacionDeLlanta } from '@/components/llantas/situacion-llanta';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type { LoteLlantas } from '@/types/llantas';

type Props = {
    /** Cada recepción con llantas es un lote; el más reciente primero. */
    lotes: LoteLlantas[];
    condiciones: EnumOption[];
    puedeRegistrar: boolean;
};

type Linea = LoteLlantas['lineas'][number];

/** Los códigos escritos, uno por línea, sin vacíos. */
function codigosDe(texto: string): string[] {
    return texto
        .split(/\r?\n/)
        .map((codigo) => codigo.trim())
        .filter((codigo) => codigo !== '');
}

/**
 * Ponerles código a las llantas de una línea de la recepción: uno por
 * renglón, tal como se marcó en cada llanta.
 */
function CodificarLinea({
    linea,
    condiciones,
    onListo,
}: {
    linea: Linea;
    condiciones: EnumOption[];
    onListo: () => void;
}) {
    const { data, setData, post, transform, processing, errors } = useForm({
        detalle_ingreso_id: linea.detalle_ingreso_id,
        condicion: 'nueva',
        texto: '',
    });
    const errores = Object.entries(
        errors as Record<string, string | undefined>,
    ).filter(([clave]) => clave.startsWith('codigos'));
    const escritos = codigosDe(data.texto).length;

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            detalle_ingreso_id: datos.detalle_ingreso_id,
            condicion: datos.condicion,
            codigos: codigosDe(datos.texto),
        }));

        post(store().url, { preserveScroll: true, onSuccess: onListo });
    };

    return (
        <form
            onSubmit={guardar}
            className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-3"
        >
            <div className="grid gap-1.5">
                <Label htmlFor={`codigos-${linea.detalle_ingreso_id}`}>
                    Códigos, uno por renglón
                </Label>
                <Textarea
                    id={`codigos-${linea.detalle_ingreso_id}`}
                    rows={Math.min(8, Math.max(3, linea.por_codificar))}
                    value={data.texto}
                    onChange={(e) => setData('texto', e.target.value)}
                    placeholder={'P-001\nP-002\nP-003'}
                    className="font-mono"
                />
                <p
                    className={cn(
                        'text-xs text-muted-foreground',
                        escritos > linea.por_codificar &&
                            'font-medium text-red-700 dark:text-red-400',
                    )}
                >
                    {escritos} de {linea.por_codificar} por codificar. Es el
                    código con que se marca cada llanta.
                </p>
                {errores.map(([clave, mensaje]) => (
                    <InputError key={clave} message={mensaje} />
                ))}
                <InputError
                    message={
                        (errors as Record<string, string | undefined>)
                            .detalle_ingreso_id
                    }
                />
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm text-muted-foreground">Son</span>
                {condiciones.map((condicion) => (
                    <button
                        key={condicion.value}
                        type="button"
                        onClick={() => setData('condicion', condicion.value)}
                        aria-pressed={data.condicion === condicion.value}
                        className={cn(
                            'min-h-9 rounded-lg border px-3 text-sm transition-colors pointer-coarse:min-h-11',
                            data.condicion === condicion.value
                                ? 'border-primary bg-primary/10 font-medium'
                                : 'hover:bg-accent',
                        )}
                    >
                        {condicion.label}
                    </button>
                ))}
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onListo}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={processing || escritos === 0}>
                    {processing && <Spinner />}
                    Registrar {escritos > 0 ? escritos : ''}{' '}
                    {escritos === 1 ? 'llanta' : 'llantas'}
                </Button>
            </div>
        </form>
    );
}

/**
 * Las llantas por lote: cada recepción del almacén con llantas es un lote.
 * Acá se le pone a cada llanta su código, y se ve dónde está cada una.
 */
export default function LlantasEnAlmacen({
    lotes,
    condiciones,
    puedeRegistrar,
}: Props) {
    const confirmar = useConfirmar();
    const [codificando, setCodificando] = useState<number | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Llantas', href: llantasRutas.index().url },
            { title: 'Lotes', href: '#' },
        ],
    });

    const quitar = async (llanta: LoteLlantas['llantas'][number]) => {
        if (
            await confirmar({
                titulo: `¿Quitar la llanta ${llanta.codigo}?`,
                descripcion:
                    'Vuelve a quedar por codificar. Sirve para corregir un código mal escrito.',
                accion: 'Quitar',
                tono: 'peligro',
            })
        ) {
            router.delete(destroy(llanta.id).url, { preserveScroll: true });
        }
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Lotes de llantas" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-lg font-semibold">Lotes de llantas</h1>
                    <p className="text-sm text-muted-foreground">
                        Cada recepción del almacén con llantas es un lote. Ponle
                        a cada llanta su código para poder montarla y seguirla.
                    </p>
                </div>
                <BuscarLlanta />
            </div>

            {lotes.length === 0 ? (
                <EmptyState
                    icono={<Boxes className="size-7" />}
                    titulo="Todavía no entró ninguna llanta al almacén"
                    descripcion="Cuando el almacén reciba una compra con artículos de la categoría Llantas, aparece acá como un lote."
                />
            ) : (
                lotes.map((lote) => {
                    const porCodificar = lote.lineas.reduce(
                        (suma, linea) => suma + linea.por_codificar,
                        0,
                    );

                    return (
                        <section
                            key={lote.id}
                            className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h2 className="font-semibold">
                                        Lote {lote.numero}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {[
                                            formatearFecha(lote.fecha),
                                            lote.proveedor,
                                            lote.documento,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                {porCodificar > 0 ? (
                                    <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        {porCodificar} sin código
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        Todas con código
                                    </span>
                                )}
                            </div>

                            <ul className="flex flex-col gap-3">
                                {lote.lineas.map((linea) => (
                                    <li
                                        key={linea.detalle_ingreso_id}
                                        className="flex flex-col gap-2"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {linea.articulo}
                                                </p>
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    {linea.cantidad}{' '}
                                                    {linea.cantidad === 1
                                                        ? 'llanta'
                                                        : 'llantas'}
                                                    {linea.marca &&
                                                        ` · ${linea.marca}`}
                                                    {linea.por_codificar > 0 &&
                                                        ` · faltan ${linea.por_codificar} por codificar`}
                                                </p>
                                            </div>
                                            {puedeRegistrar &&
                                                linea.por_codificar > 0 &&
                                                codificando !==
                                                    linea.detalle_ingreso_id && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            setCodificando(
                                                                linea.detalle_ingreso_id,
                                                            )
                                                        }
                                                    >
                                                        <Tag />
                                                        Poner códigos
                                                    </Button>
                                                )}
                                        </div>
                                        {codificando ===
                                            linea.detalle_ingreso_id && (
                                            <CodificarLinea
                                                linea={linea}
                                                condiciones={condiciones}
                                                onListo={() =>
                                                    setCodificando(null)
                                                }
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>

                            {lote.llantas.length > 0 && (
                                <ul className="grid gap-2 border-t pt-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {lote.llantas.map((llanta) => (
                                        <li
                                            key={llanta.id}
                                            className="flex items-center gap-2 rounded-lg border p-2.5"
                                        >
                                            <Link
                                                href={ficha(llanta.id)}
                                                className="flex min-w-0 flex-1 flex-col gap-1"
                                            >
                                                <span className="flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-sm font-semibold underline-offset-2 hover:underline">
                                                        {llanta.codigo}
                                                    </span>
                                                    <SituacionDeLlanta
                                                        situacion={
                                                            llanta.situacion
                                                        }
                                                    />
                                                </span>
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {llanta.situacion ===
                                                    'almacen'
                                                        ? llanta.articulo
                                                        : llanta.donde}
                                                </span>
                                            </Link>
                                            {puedeRegistrar &&
                                                llanta.situacion ===
                                                    'almacen' && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Quitar la llanta ${llanta.codigo}`}
                                                        onClick={() =>
                                                            quitar(llanta)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    );
                })
            )}
        </div>
    );
}
