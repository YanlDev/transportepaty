import { Head, Link, setLayoutProps } from '@inertiajs/react';
import llantasRutas from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { BuscarLlanta } from '@/components/llantas/buscar-llanta';
import { colorLlanta, formatearMm } from '@/components/llantas/esquema-llantas';
import { SituacionDeLlanta } from '@/components/llantas/situacion-llanta';
import { formatearKm } from '@/components/mantenimiento/estado-mantenimiento';
import { Button } from '@/components/ui/button';
import { formatearFecha, formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EstadoLlanta, SituacionLlanta } from '@/types/llantas';

type Props = {
    llanta: {
        id: number;
        codigo: string | null;
        marca: string | null;
        medida: string | null;
        articulo: string | null;
        condicion: string;
        origen: string;
        situacion: SituacionLlanta;
        donde: string;
        /** La unidad en que está montada ahora. */
        vehiculo_id: number | null;
        instalada_el: string | null;
        km_recorridos: number;
        /** Algún montaje no tiene km de instalación o de retiro. */
        km_incompleto: boolean;
        profundidad_mm: number | null;
        medida_el: string | null;
        estado: EstadoLlanta | null;
        costo: number | null;
        costo_por_km: number | null;
        lote: {
            numero: string;
            fecha: string;
            proveedor: string | null;
        } | null;
        vale: string | null;
    };
    /** Del más reciente al más antiguo. */
    movimientos: {
        id: number;
        tipo: string;
        tipo_label: string;
        fecha: string;
        placa: string | null;
        vehiculo_id: number;
        posicion: string;
        posicion_destino: string | null;
        kilometraje: number | null;
        profundidad_mm: number | null;
        observacion: string | null;
        usuario: string | null;
    }[];
};

function Dato({
    titulo,
    children,
    nota,
}: {
    titulo: string;
    children: React.ReactNode;
    nota?: string | null;
}) {
    return (
        <div className="flex flex-col gap-0.5 rounded-xl border bg-card p-3">
            <span className="text-xs text-muted-foreground">{titulo}</span>
            <span className="text-base font-semibold tabular-nums">
                {children}
            </span>
            {nota && (
                <span className="text-xs text-muted-foreground">{nota}</span>
            )}
        </div>
    );
}

/**
 * La ficha de una llanta: dónde está, cuánto rodó, cuánto dibujo le queda,
 * de qué lote es y todo lo que se le hizo, en cualquier unidad.
 */
export default function FichaLlanta({ llanta, movimientos }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Llantas', href: llantasRutas.index().url },
            { title: llanta.codigo ?? `Llanta ${llanta.id}`, href: '#' },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Llanta ${llanta.codigo ?? llanta.id}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="font-mono text-2xl font-semibold">
                            {llanta.codigo ?? `Llanta ${llanta.id}`}
                        </h1>
                        <SituacionDeLlanta situacion={llanta.situacion} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {[llanta.marca, llanta.medida, llanta.condicion]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <p className="text-sm">{llanta.donde}</p>
                </div>
                <div className="flex flex-col gap-2 sm:items-end">
                    <BuscarLlanta />
                    {llanta.vehiculo_id !== null && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={llantasRutas.show(llanta.vehiculo_id)}>
                                Ver las llantas de la unidad
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Dato
                    titulo="Kilómetros rodados"
                    nota={
                        llanta.km_incompleto
                            ? 'Falta el km de algún montaje o retiro: el total puede ser mayor.'
                            : null
                    }
                >
                    {formatearKm(llanta.km_recorridos)}
                </Dato>
                <Dato
                    titulo="Dibujo"
                    nota={
                        llanta.medida_el
                            ? `Medida el ${formatearFecha(llanta.medida_el)}`
                            : 'Sin medir'
                    }
                >
                    <span className="flex items-center gap-2">
                        {llanta.estado && (
                            <span
                                className={cn(
                                    'size-2.5 rounded-full',
                                    colorLlanta[llanta.estado],
                                )}
                            />
                        )}
                        {formatearMm(llanta.profundidad_mm)}
                    </span>
                </Dato>
                <Dato
                    titulo="Costo"
                    nota={
                        llanta.costo_por_km !== null
                            ? `S/ ${llanta.costo_por_km.toFixed(4)} por km`
                            : null
                    }
                >
                    {llanta.costo === null ? '—' : formatearSoles(llanta.costo)}
                </Dato>
                <Dato
                    titulo="Lote"
                    nota={
                        llanta.lote
                            ? [
                                  formatearFecha(llanta.lote.fecha),
                                  llanta.lote.proveedor,
                              ]
                                  .filter(Boolean)
                                  .join(' · ')
                            : llanta.origen
                    }
                >
                    {llanta.lote?.numero ?? '—'}
                </Dato>
            </div>

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Historial</h2>
                {movimientos.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Todavía no se montó en ninguna unidad.
                        {llanta.vale &&
                            ` Salió del almacén con ${llanta.vale}.`}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-xl border bg-card">
                        {movimientos.map((movimiento) => (
                            <li
                                key={movimiento.id}
                                className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {movimiento.tipo_label}
                                        {movimiento.placa && (
                                            <>
                                                {' '}
                                                en{' '}
                                                <Link
                                                    href={llantasRutas.show(
                                                        movimiento.vehiculo_id,
                                                    )}
                                                    className="underline underline-offset-2"
                                                >
                                                    {formatearPlaca(
                                                        movimiento.placa,
                                                    )}
                                                </Link>
                                            </>
                                        )}
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            · posición {movimiento.posicion}
                                            {movimiento.posicion_destino &&
                                                ` → ${movimiento.posicion_destino}`}
                                        </span>
                                    </p>
                                    {movimiento.observacion && (
                                        <p className="text-sm text-muted-foreground">
                                            {movimiento.observacion}
                                        </p>
                                    )}
                                </div>
                                <p className="shrink-0 text-xs text-muted-foreground tabular-nums sm:text-right">
                                    {[
                                        formatearFecha(movimiento.fecha),
                                        movimiento.kilometraje !== null &&
                                            formatearKm(movimiento.kilometraje),
                                        movimiento.profundidad_mm !== null &&
                                            formatearMm(
                                                movimiento.profundidad_mm,
                                            ),
                                        movimiento.usuario,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
