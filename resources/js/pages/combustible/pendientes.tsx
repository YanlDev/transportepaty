import { Head } from '@inertiajs/react';
import { Fuel, Receipt, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { pendientes } from '@/actions/App/Http/Controllers/CargaCombustibleController';
import { EliminarCargaDialog } from '@/components/combustible/eliminar-carga-dialog';
import { ProcesarCargaDialog } from '@/components/combustible/procesar-carga-dialog';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { formatearFechaHora } from '@/lib/format';
import type { CargaCombustible } from '@/types/fleet';

type CargaPendiente = CargaCombustible & {
    vehiculo: { id: number; placa: string; marca: string; modelo: string };
    odometro_sugerido: number;
};

type Props = {
    cargas: CargaPendiente[];
};

export default function CombustiblePendientes({ cargas }: Props) {
    const [procesando, setProcesando] = useState<CargaPendiente | null>(null);
    const [eliminando, setEliminando] = useState<CargaPendiente | null>(null);

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Cargas por procesar" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Cargas por procesar
                </h1>
                <p className="text-sm text-muted-foreground">
                    Cargas que subieron los conductores y faltan completar.
                </p>
            </div>

            {cargas.length === 0 ? (
                <EmptyState
                    icon={<Fuel className="size-6" />}
                    text="No hay cargas por procesar. ¡Todo al día!"
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {cargas.map((carga) => (
                        <li
                            key={carga.id}
                            className="flex items-center gap-4 rounded-xl border border-border bg-card p-4"
                        >
                            <div className="flex shrink-0 gap-1.5">
                                <Miniatura
                                    url={carga.comprobante_url}
                                    thumb={carga.comprobante_thumb}
                                    titulo="Comprobante"
                                />
                                <Miniatura
                                    url={carga.odometro_foto_url}
                                    thumb={carga.odometro_foto_thumb}
                                    titulo="Odómetro"
                                />
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="font-mono text-sm font-semibold text-foreground">
                                    {carga.vehiculo.placa}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {formatearFechaHora(carga.fecha_carga)}
                                    {carga.registrada_por
                                        ? ` · ${carga.registrada_por}`
                                        : ''}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                <Button
                                    className="bg-emerald-800 hover:bg-emerald-900"
                                    onClick={() => setProcesando(carga)}
                                >
                                    Procesar
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-9 text-destructive hover:text-destructive"
                                    title="Eliminar"
                                    onClick={() => setEliminando(carga)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {procesando && (
                <ProcesarCargaDialog
                    vehiculoId={procesando.vehiculo.id}
                    carga={procesando}
                    odometroSugerido={procesando.odometro_sugerido}
                    onClose={() => setProcesando(null)}
                />
            )}

            {eliminando && (
                <EliminarCargaDialog
                    vehiculoId={eliminando.vehiculo.id}
                    carga={eliminando}
                    onClose={() => setEliminando(null)}
                />
            )}
        </div>
    );
}

function Miniatura({
    url,
    thumb,
    titulo,
}: {
    url: string | null;
    thumb: string | null;
    titulo: string;
}) {
    if (!url) {
        return (
            <span className="grid size-12 place-items-center rounded-lg bg-muted text-muted-foreground">
                <Receipt className="size-4" />
            </span>
        );
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            title={`Ver ${titulo}`}
            className="size-12 overflow-hidden rounded-lg border border-border"
        >
            <img
                src={thumb ?? url}
                alt={titulo}
                className="size-full object-cover"
            />
        </a>
    );
}

CombustiblePendientes.layout = {
    breadcrumbs: [{ title: 'Cargas por procesar', href: pendientes().url }],
};
