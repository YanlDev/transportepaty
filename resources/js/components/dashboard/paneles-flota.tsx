import { FileWarning, Truck } from 'lucide-react';
import { lazy, Suspense } from 'react';
import type { Documentos, Unidades } from '@/types/dashboard';

/**
 * La dona vive en el chunk de los gráficos: se trae aparte para no arrastrar
 * recharts hasta que estos paneles se pinten.
 */
const DonaLazy = lazy(async () => {
    const modulo = await import('@/components/dashboard/graficos-viajes');

    return { default: modulo.Dona };
});

function DonaCargando() {
    return <div className="h-[132px] animate-pulse rounded-lg bg-muted" />;
}

export function DocumentosPanel({ documentos }: { documentos: Documentos }) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <FileWarning className="size-4 text-muted-foreground" />
                    Estado de documentos
                </h2>
            </div>
            <div className="p-4">
                <Suspense fallback={<DonaCargando />}>
                    <DonaLazy
                        total={documentos.total}
                        etiquetaTotal="documentos"
                        datos={[
                            {
                                clave: 'vigentes',
                                label: 'Vigentes',
                                valor: documentos.vigentes,
                                color: 'var(--color-emerald-500)',
                            },
                            {
                                clave: 'vencidos',
                                label: 'Vencidos',
                                valor: documentos.vencidos,
                                color: 'var(--color-red-500)',
                            },
                            {
                                clave: 'por_vencer',
                                label: 'Por vencer (≤ 15 días)',
                                valor: documentos.por_vencer,
                                color: 'var(--color-amber-500)',
                            },
                            {
                                clave: 'sin_fecha',
                                label: 'Sin fecha',
                                valor: documentos.sin_fecha,
                                color: 'var(--color-zinc-400)',
                            },
                        ]}
                    />
                </Suspense>
            </div>
        </section>
    );
}

export function UnidadesPanel({ unidades }: { unidades: Unidades }) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Truck className="size-4 text-muted-foreground" />
                    Unidades operativas
                </h2>
            </div>
            <div className="p-4">
                <Suspense fallback={<DonaCargando />}>
                    <DonaLazy
                        total={unidades.total}
                        etiquetaTotal="unidades"
                        datos={[
                            {
                                clave: 'operativas',
                                label: 'Operativas',
                                valor: unidades.operativas,
                                color: 'var(--color-emerald-500)',
                            },
                            {
                                clave: 'no_programables',
                                label: 'No programables',
                                valor: unidades.no_programables,
                                color: 'var(--color-amber-500)',
                            },
                            {
                                clave: 'con_documentos_vencidos',
                                label: 'Con documentos vencidos',
                                valor: unidades.con_documentos_vencidos,
                                color: 'var(--color-red-500)',
                            },
                        ]}
                    />
                </Suspense>
            </div>
        </section>
    );
}
