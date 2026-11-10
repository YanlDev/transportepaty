import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, CircleCheck, CircleDot, Search } from 'lucide-react';
import { useState } from 'react';
import llantas from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { EmptyState } from '@/components/empty-state';
import { CortesLlantasDialog } from '@/components/llantas/cortes-llantas-dialog';
import { formatearMm } from '@/components/llantas/esquema-llantas';
import { GrupoDeUnidades } from '@/components/mantenimiento/grupo-de-unidades';
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
 * Las llantas de la flota por lo que hay que hacer: primero las unidades
 * con llantas para cambiar, después las de revisar y las que falta medir.
 * Las que están al día y las que nunca se cargaron quedan plegadas.
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

            <p className="-mt-3 text-xs text-muted-foreground">
                Se cambia con {formatearMm(umbrales.cambiar_mm)} o menos y se
                revisa con {formatearMm(umbrales.revisar_mm)} o menos.
            </p>

            {texto === '' &&
                visibles.length > 0 &&
                !visibles.some(
                    (unidad) =>
                        GRUPOS.find((grupo) => grupo.clave === grupoDe(unidad))
                            ?.abierto,
                ) && (
                    <p className="flex items-center gap-2 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                        <CircleCheck className="size-4 shrink-0 text-emerald-600" />
                        Nada por atender: sin llantas por cambiar, revisar ni
                        medir.
                    </p>
                )}

            {visibles.length === 0 ? (
                <EmptyState
                    icono={<CircleDot className="size-7" />}
                    titulo="Ninguna unidad con esa placa"
                    descripcion="Revisa la placa o cambia entre tractos y carretas."
                />
            ) : (
                GRUPOS.map((grupo) => {
                    const delGrupo = visibles.filter(
                        (unidad) => grupoDe(unidad) === grupo.clave,
                    );

                    return (
                        <GrupoDeUnidades
                            key={`${tipo}-${grupo.clave}`}
                            titulo={grupo.titulo}
                            cantidad={delGrupo.length}
                            tono={grupo.tono}
                            ayuda={grupo.ayuda}
                            abiertoAlInicio={grupo.abierto}
                            forzarAbierto={texto !== ''}
                        >
                            <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                {delGrupo.map((unidad) => (
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
                                                    {formatearPlaca(
                                                        unidad.placa,
                                                    )}
                                                    {unidad.marca && (
                                                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                            {unidad.marca}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="flex flex-wrap gap-x-3 text-xs text-muted-foreground tabular-nums">
                                                    <span>
                                                        {unidad.montadas} de{' '}
                                                        {unidad.posiciones}{' '}
                                                        llantas
                                                    </span>
                                                    {unidad.cambiar > 0 && (
                                                        <span className="font-medium text-red-700 dark:text-red-400">
                                                            {unidad.cambiar}{' '}
                                                            para cambiar
                                                        </span>
                                                    )}
                                                    {unidad.revisar > 0 && (
                                                        <span className="font-medium text-amber-700 dark:text-amber-400">
                                                            {unidad.revisar}{' '}
                                                            para revisar
                                                        </span>
                                                    )}
                                                    {unidad.sin_medir > 0 && (
                                                        <span>
                                                            {unidad.sin_medir}{' '}
                                                            sin medir
                                                        </span>
                                                    )}
                                                </span>
                                            </div>
                                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </GrupoDeUnidades>
                    );
                })
            )}
        </div>
    );
}

type ClaveGrupo = 'cambiar' | 'revisar' | 'sin_medir' | 'al_dia' | 'sin_cargar';

/** Lo peor que tiene la unidad decide en qué grupo cae. */
function grupoDe(unidad: UnidadLlantas): ClaveGrupo {
    if (unidad.cambiar > 0) {
        return 'cambiar';
    }

    if (unidad.revisar > 0) {
        return 'revisar';
    }

    if (unidad.montadas === 0) {
        return 'sin_cargar';
    }

    return unidad.sin_medir > 0 ? 'sin_medir' : 'al_dia';
}

/** En el orden en que se atienden; lo que no pide nada queda plegado. */
const GRUPOS: {
    clave: ClaveGrupo;
    titulo: string;
    tono: 'mal' | 'aviso' | 'bien' | 'neutro';
    ayuda?: string;
    abierto: boolean;
}[] = [
    {
        clave: 'cambiar',
        titulo: 'Con llantas para cambiar',
        tono: 'mal',
        abierto: true,
    },
    {
        clave: 'revisar',
        titulo: 'Con llantas para revisar',
        tono: 'aviso',
        abierto: true,
    },
    {
        clave: 'sin_medir',
        titulo: 'Con llantas sin medir',
        tono: 'aviso',
        ayuda: 'Tienen llantas cargadas, pero a alguna le falta la medida.',
        abierto: true,
    },
    { clave: 'al_dia', titulo: 'Al día', tono: 'bien', abierto: false },
    {
        clave: 'sin_cargar',
        titulo: 'Sin llantas cargadas',
        tono: 'neutro',
        ayuda: 'Todavía no se registró qué llantas tienen puestas.',
        abierto: false,
    },
];
