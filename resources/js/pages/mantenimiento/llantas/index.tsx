import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, CircleDot, Search } from 'lucide-react';
import { useState } from 'react';
import llantas from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { EmptyState } from '@/components/empty-state';
import { CortesLlantasDialog } from '@/components/llantas/cortes-llantas-dialog';
import { formatearMm } from '@/components/llantas/esquema-llantas';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { UmbralesLlanta, UnidadLlantas } from '@/types/llantas';

type Props = {
    /** Ordenadas: las que tienen llantas para cambiar primero. */
    unidades: UnidadLlantas[];
    umbrales: UmbralesLlanta;
};

const tipos = [
    { valor: 'tracto', etiqueta: 'Tractos' },
    { valor: 'carreta', etiqueta: 'Carretas' },
] as const;

/**
 * Las llantas de la flota, unidad por unidad: cuántas tiene puestas y
 * cuántas hay que cambiar o revisar. Lo urgente viene arriba.
 */
export default function LlantasIndex({ unidades, umbrales }: Props) {
    const { puede } = usePermisos();
    const [tipo, setTipo] = useState<'tracto' | 'carreta'>('tracto');
    const [buscar, setBuscar] = useState('');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Llantas', href: llantas.index().url },
        ],
    });

    const delTipo = unidades.filter((unidad) => unidad.tipo === tipo);
    const texto = buscar.replace(/[\s-]/g, '').toUpperCase();
    const visibles = delTipo.filter((unidad) =>
        unidad.placa.replace(/[\s-]/g, '').toUpperCase().includes(texto),
    );

    const cambiar = delTipo.reduce((suma, u) => suma + u.cambiar, 0);
    const revisar = delTipo.reduce((suma, u) => suma + u.revisar, 0);
    const sinCargar = delTipo.filter((u) => u.montadas === 0).length;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Llantas" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="grid w-full grid-cols-2 gap-2 sm:w-72">
                    {tipos.map((opcion) => (
                        <button
                            key={opcion.valor}
                            type="button"
                            onClick={() => setTipo(opcion.valor)}
                            aria-pressed={tipo === opcion.valor}
                            className={cn(
                                'h-10 rounded-lg border text-sm font-medium transition-colors',
                                tipo === opcion.valor
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            {opcion.etiqueta} (
                            {
                                unidades.filter((u) => u.tipo === opcion.valor)
                                    .length
                            }
                            )
                        </button>
                    ))}
                </div>
                <div className="flex gap-2">
                    <div className="relative flex-1 sm:w-64">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar placa"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    {puede('mantenimiento.configurar') && (
                        <CortesLlantasDialog umbrales={umbrales} />
                    )}
                </div>
            </div>

            <div className="grid grid-cols-3 gap-2">
                <Cifra
                    valor={cambiar}
                    etiqueta={`Para cambiar (≤ ${formatearMm(umbrales.cambiar_mm)})`}
                    className="text-red-700 dark:text-red-400"
                />
                <Cifra
                    valor={revisar}
                    etiqueta={`Para revisar (≤ ${formatearMm(umbrales.revisar_mm)})`}
                    className="text-amber-700 dark:text-amber-400"
                />
                <Cifra
                    valor={sinCargar}
                    etiqueta="Unidades sin llantas cargadas"
                    className="text-muted-foreground"
                />
            </div>

            {visibles.length === 0 ? (
                <EmptyState
                    icono={<CircleDot className="size-7" />}
                    titulo="Ninguna unidad con esa placa"
                    descripcion="Revisa la placa o cambia entre tractos y carretas."
                />
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    {visibles.map((unidad) => (
                        <li key={unidad.id}>
                            <Link
                                href={llantas.show(unidad.id)}
                                className={cn(
                                    'flex items-center gap-3 rounded-xl border border-l-4 bg-card p-3 transition-colors hover:bg-accent/50',
                                    unidad.cambiar > 0
                                        ? 'border-l-red-500'
                                        : unidad.revisar > 0
                                          ? 'border-l-amber-500'
                                          : unidad.montadas > 0 &&
                                              unidad.sin_medir === 0
                                            ? 'border-l-emerald-500'
                                            : 'border-l-zinc-300 dark:border-l-zinc-600',
                                )}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="font-semibold tracking-wide">
                                        {formatearPlaca(unidad.placa)}
                                        {unidad.marca && (
                                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                {unidad.marca}
                                            </span>
                                        )}
                                    </span>
                                    <span className="flex flex-wrap gap-x-3 text-xs text-muted-foreground tabular-nums">
                                        <span>
                                            {unidad.montadas} de{' '}
                                            {unidad.posiciones} llantas
                                        </span>
                                        {unidad.cambiar > 0 && (
                                            <span className="font-medium text-red-700 dark:text-red-400">
                                                {unidad.cambiar} para cambiar
                                            </span>
                                        )}
                                        {unidad.revisar > 0 && (
                                            <span className="font-medium text-amber-700 dark:text-amber-400">
                                                {unidad.revisar} para revisar
                                            </span>
                                        )}
                                        {unidad.sin_medir > 0 && (
                                            <span>
                                                {unidad.sin_medir} sin medir
                                            </span>
                                        )}
                                    </span>
                                </div>
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Cifra({
    valor,
    etiqueta,
    className,
}: {
    valor: number;
    etiqueta: string;
    className: string;
}) {
    return (
        <div className="flex flex-col rounded-xl border bg-card p-3">
            <span
                className={cn('text-2xl font-semibold tabular-nums', className)}
            >
                {valor}
            </span>
            <span className="text-xs text-muted-foreground">{etiqueta}</span>
        </div>
    );
}
