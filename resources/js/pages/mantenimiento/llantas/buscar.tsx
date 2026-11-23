import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, CircleDot } from 'lucide-react';
import { ficha } from '@/actions/App/Http/Controllers/LlantaAlmacenController';
import llantasRutas from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { EmptyState } from '@/components/empty-state';
import { BuscarLlanta } from '@/components/llantas/buscar-llanta';
import { SituacionDeLlanta } from '@/components/llantas/situacion-llanta';
import type { SituacionLlanta } from '@/types/llantas';

type Props = {
    codigo: string;
    llantas: {
        id: number;
        codigo: string | null;
        descripcion: string;
        situacion: SituacionLlanta;
        donde: string;
    }[];
};

/** Las llantas cuyo código contiene lo que se buscó. */
export default function BuscarLlantas({ codigo, llantas }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Llantas', href: llantasRutas.index().url },
            { title: 'Buscar', href: '#' },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Buscar llanta" />
            <BuscarLlanta inicial={codigo} />

            {llantas.length === 0 ? (
                <EmptyState
                    icono={<CircleDot className="size-7" />}
                    titulo={
                        codigo === ''
                            ? 'Escribe el código de la llanta'
                            : `Ninguna llanta con el código «${codigo}»`
                    }
                    descripcion="Es el código con que la marcaron al recibirla o al cargarla en su unidad."
                />
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    {llantas.map((llanta) => (
                        <li key={llanta.id}>
                            <Link
                                href={ficha(llanta.id)}
                                className="flex items-center gap-3 rounded-xl border bg-card p-3 transition-colors hover:bg-accent/50"
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <span className="font-mono font-semibold">
                                            {llanta.codigo}
                                        </span>
                                        <SituacionDeLlanta
                                            situacion={llanta.situacion}
                                        />
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {[llanta.descripcion, llanta.donde]
                                            .filter(Boolean)
                                            .join(' · ')}
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
