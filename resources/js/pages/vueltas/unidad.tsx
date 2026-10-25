import { Head, setLayoutProps } from '@inertiajs/react';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { Vuelta } from '@/types/vueltas';

type Props = {
    tracto: { id: number; placa: string };
    /** De la más reciente a la más antigua. */
    vueltas: Vuelta[];
};

/**
 * La historia de una unidad, vuelta por vuelta, con las GR de cada parada.
 */
export default function VueltasUnidad({ tracto, vueltas }: Props) {
    const placa = formatearPlaca(tracto.placa);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vueltas', href: vueltasRutas.index().url },
            { title: placa, href: vueltasRutas.unidad(tracto.id).url },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Vueltas ${placa}`} />

            <p className="text-sm text-muted-foreground">
                {vueltas.length} {vueltas.length === 1 ? 'vuelta' : 'vueltas'}{' '}
                de <span className="font-mono">{placa}</span>
            </p>

            {vueltas.length === 0 ? (
                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Esta unidad todavía no tiene vueltas.
                </p>
            ) : (
                <ol className="flex flex-col gap-4">
                    {vueltas.map((vuelta) => (
                        <li
                            key={vuelta.id}
                            className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                        >
                            <header className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span className="font-mono font-semibold">
                                    {vuelta.codigo}
                                </span>
                                <EstadoVueltaBadge vuelta={vuelta} />
                                <span className="text-sm text-muted-foreground">
                                    {vuelta.tipo_label}
                                </span>
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {formatearFecha(vuelta.inicio)}
                                    {vuelta.fin &&
                                        ` – ${formatearFecha(vuelta.fin)}`}
                                    {vuelta.dias !== null &&
                                        ` · ${vuelta.dias} ${vuelta.dias === 1 ? 'día' : 'días'}`}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {vuelta.conductor ?? 'Sin conductor'}
                                    {vuelta.carreta &&
                                        ` · carreta ${formatearPlaca(vuelta.carreta)}`}
                                </span>
                            </header>

                            <TramosVuelta vuelta={vuelta} conGuias />
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
