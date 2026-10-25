import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarPlus, ChevronRight, Pencil, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { OpcionesVuelta, UnidadConVuelta, Vuelta } from '@/types/vueltas';

type Props = OpcionesVuelta & {
    /** Cada unidad con la vuelta en que va y la próxima programada. */
    unidades: UnidadConVuelta[];
};

/** El diálogo abierto: para programar una nueva o editar una existente. */
type Dialogo = { vuelta: Vuelta | null } | null;

function CabeceraVuelta({
    vuelta,
    onEditar,
}: {
    vuelta: Vuelta;
    onEditar: (vuelta: Vuelta) => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <EstadoVueltaBadge vuelta={vuelta} />
            <span className="text-xs text-muted-foreground">
                {vuelta.codigo} · {vuelta.tipo_label}
                {vuelta.inicio &&
                    ` · ${vuelta.estado === 'programada' ? 'sale' : 'desde'} ${formatearFecha(vuelta.inicio)}`}
            </span>
            {vuelta.editable && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto h-7"
                    onClick={() => onEditar(vuelta)}
                >
                    <Pencil className="size-3.5" />
                    Editar
                </Button>
            )}
        </div>
    );
}

/**
 * En qué anda cada unidad: la vuelta en la que va, con su ida y su regreso,
 * y la que tiene programada después. Desde acá se programa la siguiente y se
 * entra a la historia de la unidad.
 */
export default function VueltasIndex({ unidades, ...opciones }: Props) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState('');
    const [dialogo, setDialogo] = useState<Dialogo>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Vueltas', href: vueltas.index().url }],
    });

    const filtradas = useMemo(() => {
        const texto = buscar.trim().toLowerCase().replace(/-/g, '');

        if (texto === '') {
            return unidades;
        }

        return unidades.filter(
            ({ tracto, vuelta, proxima }) =>
                tracto.placa.toLowerCase().replace(/-/g, '').includes(texto) ||
                (vuelta?.conductor ?? '').toLowerCase().includes(texto) ||
                (proxima?.conductor ?? '').toLowerCase().includes(texto),
        );
    }, [buscar, unidades]);

    const enCurso = unidades.filter(
        ({ vuelta }) => vuelta?.estado === 'en_curso',
    ).length;
    const programadas = unidades.filter(({ proxima }) => proxima).length;

    const editar = (vuelta: Vuelta) => setDialogo({ vuelta });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Vueltas" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {unidades.length}{' '}
                    {unidades.length === 1 ? 'unidad' : 'unidades'} · {enCurso}{' '}
                    en curso · {programadas} con la próxima programada
                </p>
                <div className="flex gap-2">
                    <div className="relative flex-1 sm:w-72">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={buscar}
                            onChange={(evento) =>
                                setBuscar(evento.target.value)
                            }
                            placeholder="Placa o conductor"
                            className="pl-8"
                        />
                    </div>
                    {puede('programacion.crear') && (
                        <Button onClick={() => setDialogo({ vuelta: null })}>
                            <CalendarPlus className="size-4" />
                            <span className="max-sm:hidden">
                                Programar vuelta
                            </span>
                        </Button>
                    )}
                </div>
            </div>

            {filtradas.length === 0 ? (
                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {unidades.length === 0
                        ? 'Todavía no hay vueltas armadas.'
                        : 'Ninguna unidad coincide con la búsqueda.'}
                </p>
            ) : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {filtradas.map(
                        ({ tracto, vueltas: total, vuelta, proxima }) => (
                            <section
                                key={tracto.id}
                                className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                            >
                                <header className="flex items-start justify-between gap-3">
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <span className="font-mono text-base font-semibold">
                                            {formatearPlaca(tracto.placa)}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {(vuelta ?? proxima)?.conductor ??
                                                'Sin conductor'}
                                        </span>
                                    </div>
                                    <Link
                                        href={vueltas.unidad(tracto.id).url}
                                        className="flex shrink-0 items-center gap-1 text-xs font-medium text-primary hover:underline"
                                    >
                                        {total}{' '}
                                        {total === 1 ? 'vuelta' : 'vueltas'}
                                        <ChevronRight className="size-3.5" />
                                    </Link>
                                </header>

                                {vuelta && (
                                    <div className="flex flex-col gap-3">
                                        <CabeceraVuelta
                                            vuelta={vuelta}
                                            onEditar={editar}
                                        />
                                        <TramosVuelta vuelta={vuelta} />
                                    </div>
                                )}

                                {proxima && (
                                    <div className="flex flex-col gap-3 border-t pt-4">
                                        <CabeceraVuelta
                                            vuelta={proxima}
                                            onEditar={editar}
                                        />
                                        <TramosVuelta vuelta={proxima} />
                                    </div>
                                )}
                            </section>
                        ),
                    )}
                </div>
            )}

            {dialogo && (
                <ProgramarVueltaDialog
                    key={dialogo.vuelta?.id ?? 'nueva'}
                    open
                    onOpenChange={(abierto) => !abierto && setDialogo(null)}
                    vuelta={dialogo.vuelta}
                    opciones={opciones}
                />
            )}
        </div>
    );
}
