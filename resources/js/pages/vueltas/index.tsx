import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Input } from '@/components/ui/input';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { UnidadConVuelta } from '@/types/vueltas';

type Props = {
    /** Cada unidad con su vuelta más reciente. */
    unidades: UnidadConVuelta[];
};

/**
 * En qué anda cada unidad: la vuelta que tiene abierta (o la última que
 * cerró), con su ida y su regreso. Desde acá se entra a la historia de la
 * unidad.
 */
export default function VueltasIndex({ unidades }: Props) {
    const [buscar, setBuscar] = useState('');

    setLayoutProps({
        breadcrumbs: [{ title: 'Vueltas', href: vueltas.index().url }],
    });

    const filtradas = useMemo(() => {
        const texto = buscar.trim().toLowerCase().replace(/-/g, '');

        if (texto === '') {
            return unidades;
        }

        return unidades.filter(
            ({ tracto, vuelta }) =>
                tracto.placa.toLowerCase().replace(/-/g, '').includes(texto) ||
                (vuelta.conductor ?? '').toLowerCase().includes(texto),
        );
    }, [buscar, unidades]);

    const enCurso = unidades.filter(
        ({ vuelta }) => vuelta.estado === 'en_curso',
    ).length;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Vueltas" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {unidades.length}{' '}
                    {unidades.length === 1 ? 'unidad' : 'unidades'} · {enCurso}{' '}
                    con la vuelta en curso
                </p>
                <div className="relative sm:w-72">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={buscar}
                        onChange={(evento) => setBuscar(evento.target.value)}
                        placeholder="Placa o conductor"
                        className="pl-8"
                    />
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
                    {filtradas.map(({ tracto, vueltas: total, vuelta }) => (
                        <section
                            key={tracto.id}
                            className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                        >
                            <header className="flex items-start justify-between gap-3">
                                <div className="flex min-w-0 flex-col gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-mono text-base font-semibold">
                                            {formatearPlaca(tracto.placa)}
                                        </span>
                                        <EstadoVueltaBadge vuelta={vuelta} />
                                        <span className="text-xs text-muted-foreground">
                                            {vuelta.codigo} ·{' '}
                                            {vuelta.tipo_label}
                                        </span>
                                    </div>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {vuelta.conductor ?? 'Sin conductor'}
                                        {vuelta.inicio &&
                                            ` · desde ${formatearFecha(vuelta.inicio)}`}
                                    </span>
                                </div>
                                <Link
                                    href={vueltas.unidad(tracto.id).url}
                                    className="flex shrink-0 items-center gap-1 text-xs font-medium text-primary hover:underline"
                                >
                                    {total} {total === 1 ? 'vuelta' : 'vueltas'}
                                    <ChevronRight className="size-3.5" />
                                </Link>
                            </header>

                            <TramosVuelta vuelta={vuelta} />
                        </section>
                    ))}
                </div>
            )}
        </div>
    );
}
