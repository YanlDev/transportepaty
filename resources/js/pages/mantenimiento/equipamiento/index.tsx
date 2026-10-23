import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, Package, Search } from 'lucide-react';
import { useState } from 'react';
import equipamiento from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { EmptyState } from '@/components/empty-state';
import { CatalogoDialog } from '@/components/equipamiento/catalogo-dialog';
import {
    bordeEquipamiento,
    EstadoEquipamientoBadge,
} from '@/components/equipamiento/estado-equipamiento';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EquipamientoCarreta, ItemCatalogo } from '@/types/equipamiento';

type Props = {
    /** Ordenadas: a las que les falta algo primero. */
    carretas: EquipamientoCarreta[];
    catalogo: ItemCatalogo[];
};

/**
 * El equipamiento de las carretas —por ahora, las fajas—: a cuáles les
 * falta algo, cuáles no se contaron y cuáles están completas.
 */
export default function EquipamientoIndex({ carretas, catalogo }: Props) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState('');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Equipamiento', href: equipamiento.index().url },
        ],
    });

    const texto = buscar.replace(/[\s-]/g, '').toUpperCase();
    const visibles = carretas.filter((carreta) =>
        carreta.placa.replace(/[\s-]/g, '').toUpperCase().includes(texto),
    );

    const conFalta = carretas.filter((c) => c.estado === 'falta').length;
    const sinContar = carretas.filter((c) => c.estado === 'sin_contar').length;
    const completas = carretas.filter((c) => c.estado === 'completo').length;
    const conMinimo = catalogo.some((item) => item.minimo !== null);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Equipamiento de carretas" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {catalogo.map((item) => item.nombre).join(' · ')}
                </p>
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
                        <CatalogoDialog
                            key={catalogo
                                .map((item) => `${item.id}:${item.minimo}`)
                                .join()}
                            catalogo={catalogo}
                        />
                    )}
                </div>
            </div>

            {!conMinimo && (
                <p className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    Todavía no hay mínimos: las carretas se cuentan, pero no
                    avisa a cuáles les falta.
                    {puede('mantenimiento.configurar') &&
                        ' Cárgalos en «Ítems y mínimos».'}
                </p>
            )}

            <div className="grid grid-cols-3 gap-2">
                <Cifra
                    valor={conFalta}
                    etiqueta="Les falta algo"
                    className="text-red-700 dark:text-red-400"
                />
                <Cifra
                    valor={sinContar}
                    etiqueta="Sin contar"
                    className="text-amber-700 dark:text-amber-400"
                />
                <Cifra
                    valor={completas}
                    etiqueta="Completas"
                    className="text-emerald-700 dark:text-emerald-400"
                />
            </div>

            {visibles.length === 0 ? (
                <EmptyState
                    icono={<Package className="size-7" />}
                    titulo="Ninguna carreta con esa placa"
                    descripcion="Revisa la placa."
                />
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    {visibles.map((carreta) => (
                        <li key={carreta.id}>
                            <Link
                                href={equipamiento.show(carreta.id)}
                                className={cn(
                                    'flex items-center gap-3 rounded-xl border border-l-4 bg-card p-3 transition-colors hover:bg-accent/50',
                                    bordeEquipamiento[carreta.estado],
                                )}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-semibold tracking-wide">
                                            {formatearPlaca(carreta.placa)}
                                        </span>
                                        <EstadoEquipamientoBadge
                                            estado={carreta.estado}
                                            label={carreta.estado_label}
                                        />
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {carreta.estado === 'sin_contar'
                                            ? 'Nunca se contó'
                                            : carreta.faltan > 0
                                              ? carreta.items
                                                    .filter((i) => i.faltan > 0)
                                                    .map(
                                                        (i) =>
                                                            `${i.faltan} ${i.nombre.toLowerCase()}`,
                                                    )
                                                    .join(' · ')
                                              : `Contada el ${formatearFecha(carreta.conteo_fecha)}`}
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
