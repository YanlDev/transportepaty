import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, CircleCheck, Package, Search } from 'lucide-react';
import { useState } from 'react';
import equipamiento from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { EmptyState } from '@/components/empty-state';
import { CatalogoDialog } from '@/components/equipamiento/catalogo-dialog';
import {
    bordeEquipamiento,
    EstadoEquipamientoBadge,
} from '@/components/equipamiento/estado-equipamiento';
import { GrupoDeUnidades } from '@/components/mantenimiento/grupo-de-unidades';
import { Button } from '@/components/ui/button';
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
 * El equipamiento de la flota: a qué unidades les falta algo, cuáles no
 * tienen levantamiento todavía y cuáles están completas.
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
    const [tipo, setTipo] = useState<'todas' | 'carreta' | 'tracto'>('todas');
    const visibles = carretas.filter(
        (carreta) =>
            (tipo === 'todas' || carreta.tipo === tipo) &&
            carreta.placa.replace(/[\s-]/g, '').toUpperCase().includes(texto),
    );

    const conMinimo = catalogo.some((item) => item.minimo !== null);
    const levantadas = carretas.filter(
        (carreta) => carreta.estado !== 'sin_contar',
    ).length;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Equipamiento de unidades" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    Levantamiento hecho en{' '}
                    <span className="font-medium text-foreground tabular-nums">
                        {levantadas} de {carretas.length}
                    </span>{' '}
                    unidades
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
                                .map(
                                    (item) =>
                                        `${item.id}:${item.minimo}:${item.aplica_a}:${item.lleva_vencimiento}:${item.articulo}`,
                                )
                                .join()}
                            catalogo={catalogo}
                        />
                    )}
                </div>
            </div>

            {!conMinimo && (
                <p className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    La dotación todavía no tiene cantidades: se hace el
                    levantamiento, pero no avisa a cuáles les falta.
                    {puede('mantenimiento.configurar') &&
                        ' Cárgalas en «Dotación».'}
                </p>
            )}

            <div className="flex gap-1.5">
                {(
                    [
                        ['todas', 'Todas'],
                        ['carreta', 'Carretas'],
                        ['tracto', 'Tractos'],
                    ] as const
                ).map(([valor, etiqueta]) => (
                    <Button
                        key={valor}
                        size="sm"
                        variant={tipo === valor ? 'default' : 'outline'}
                        onClick={() => setTipo(valor)}
                    >
                        {etiqueta}
                    </Button>
                ))}
            </div>

            {texto === '' &&
                visibles.length > 0 &&
                !visibles.some(
                    (carreta) =>
                        GRUPOS.find((grupo) => grupo.clave === grupoDe(carreta))
                            ?.abierto,
                ) && (
                    <p className="flex items-center gap-2 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                        <CircleCheck className="size-4 shrink-0 text-emerald-600" />
                        Nada por atender: sin equipamiento faltante ni vencido.
                    </p>
                )}

            {visibles.length === 0 ? (
                <EmptyState
                    icono={<Package className="size-7" />}
                    titulo="Ninguna unidad con esa placa"
                    descripcion="Revisa la placa."
                />
            ) : (
                GRUPOS.map((grupo) => {
                    const delGrupo = visibles.filter(
                        (carreta) => grupoDe(carreta) === grupo.clave,
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
                                {delGrupo.map((carreta) => (
                                    <li key={carreta.id}>
                                        <Link
                                            href={equipamiento.show(carreta.id)}
                                            className={cn(
                                                'flex items-center gap-3 rounded-xl border border-l-4 bg-card p-3 transition-colors hover:bg-accent/50',
                                                bordeEquipamiento[
                                                    carreta.estado
                                                ],
                                            )}
                                        >
                                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="font-semibold tracking-wide">
                                                        {formatearPlaca(
                                                            carreta.placa,
                                                        )}
                                                    </span>
                                                    <EstadoEquipamientoBadge
                                                        estado={carreta.estado}
                                                        label={
                                                            carreta.estado_label
                                                        }
                                                    />
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {carreta.estado ===
                                                    'sin_contar'
                                                        ? 'Sin levantamiento'
                                                        : carreta.faltan > 0
                                                          ? carreta.items
                                                                .filter(
                                                                    (i) =>
                                                                        i.faltan >
                                                                        0,
                                                                )
                                                                .map(
                                                                    (i) =>
                                                                        `${i.faltan} ${i.nombre.toLowerCase()}`,
                                                                )
                                                                .join(' · ')
                                                          : carreta.vencidos > 0
                                                            ? carreta.items
                                                                  .filter(
                                                                      (i) =>
                                                                          i.vencido,
                                                                  )
                                                                  .map(
                                                                      (i) =>
                                                                          `${i.nombre.toLowerCase()} vencido`,
                                                                  )
                                                                  .join(' · ')
                                                            : carreta.conteo_fecha
                                                              ? `Levantamiento del ${formatearFecha(carreta.conteo_fecha)}`
                                                              : 'Con equipamiento del almacén, sin contar'}
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

type ClaveGrupo = 'atender' | 'sin_contar' | 'contado' | 'completo';

/** Algo vencido pesa igual que algo que falta: las dos se atienden. */
function grupoDe(carreta: EquipamientoCarreta): ClaveGrupo {
    if (carreta.estado === 'falta' || carreta.vencidos > 0) {
        return 'atender';
    }

    return carreta.estado === 'sin_contar'
        ? 'sin_contar'
        : carreta.estado === 'completo'
          ? 'completo'
          : 'contado';
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
        clave: 'atender',
        titulo: 'Les falta algo o lo tienen vencido',
        tono: 'mal',
        abierto: true,
    },
    {
        clave: 'contado',
        titulo: 'Con levantamiento, sin cantidad con qué comparar',
        tono: 'aviso',
        ayuda: 'Tienen levantamiento, pero su dotación no dice cuánto deben llevar.',
        abierto: false,
    },
    { clave: 'completo', titulo: 'Completas', tono: 'bien', abierto: false },
    {
        clave: 'sin_contar',
        titulo: 'Sin levantamiento',
        tono: 'aviso',
        ayuda: 'Todavía no se revisó qué llevan: se hace cuando la unidad está en base.',
        abierto: true,
    },
];
