import { PieChart as PieChartIcon } from 'lucide-react';
import { lazy, Suspense } from 'react';
import type { RanuraDocumental } from '@/types/fleet';

/**
 * La dona vive en el chunk de los gráficos del tablero: se trae aparte para no
 * cargar recharts hasta que se pinte.
 */
const DonaLazy = lazy(async () => {
    const modulo = await import('@/components/dashboard/graficos-viajes');

    return { default: modulo.Dona };
});

/**
 * El expediente del conductor contado por estado, con los mismos colores que
 * el tablero: un «vencido» se ve igual en todo el sistema. Solo cuenta lo
 * exigible; un DILER sin cargar no es una falta.
 */
export function ConductorResumenDocumentos({
    ranuras,
}: {
    ranuras: RanuraDocumental[];
}) {
    const exigibles = ranuras.filter((ranura) => ranura.obligatorio);
    const cuenta = (estado: RanuraDocumental['estado']) =>
        exigibles.filter((ranura) => ranura.estado === estado).length;

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <PieChartIcon className="size-4 text-muted-foreground" />
                    Estado de documentos
                </h2>
            </div>
            <div className="p-4">
                <Suspense
                    fallback={
                        <div className="h-[132px] animate-pulse rounded-lg bg-muted" />
                    }
                >
                    <DonaLazy
                        total={exigibles.length}
                        etiquetaTotal="exigibles"
                        datos={[
                            {
                                clave: 'vigente',
                                label: 'Vigentes',
                                valor: cuenta('vigente'),
                                color: 'var(--color-emerald-500)',
                            },
                            {
                                clave: 'por_vencer',
                                label: 'Por vencer',
                                valor: cuenta('por_vencer'),
                                color: 'var(--color-amber-500)',
                            },
                            {
                                clave: 'vencido',
                                label: 'Vencidos',
                                valor: cuenta('vencido'),
                                color: 'var(--color-red-500)',
                            },
                            {
                                clave: 'faltante',
                                label: 'Faltan',
                                valor: cuenta('faltante'),
                                color: 'var(--color-zinc-400)',
                            },
                        ]}
                    />
                </Suspense>
            </div>
        </section>
    );
}
