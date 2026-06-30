import { AlertTriangle, Clock, Pencil, Receipt, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatearFechaHora, formatearSoles } from '@/lib/format';
import type { CargaCombustible } from '@/types/fleet';

type Props = {
    cargas: CargaCombustible[];
    puedeGestionar: boolean;
    onProcesar: (carga: CargaCombustible) => void;
    onEliminar: (carga: CargaCombustible) => void;
};

export function TablaCargas({
    cargas,
    puedeGestionar,
    onProcesar,
    onEliminar,
}: Props) {
    return (
        <ul className="flex flex-col gap-3">
            {cargas.map((carga) => (
                <li
                    key={carga.id}
                    className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="flex min-w-0 flex-1 items-start gap-3">
                        <Fotos carga={carga} />

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium text-foreground">
                                    {formatearFechaHora(carga.fecha_carga)}
                                </span>
                                <EstadoBadge carga={carga} />
                            </div>

                            {carga.procesada ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {carga.odometro?.toLocaleString('es-PE')} km
                                    · {carga.galones} gal ·{' '}
                                    {formatearSoles(carga.costo_total)}
                                    {carga.rendimiento !== null && (
                                        <>
                                            {' '}
                                            ·{' '}
                                            <span className="font-medium text-foreground">
                                                {carga.rendimiento} km/gal
                                            </span>
                                        </>
                                    )}
                                </p>
                            ) : (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Registrada por {carga.registrada_por ?? '—'}
                                    . Falta completar los datos.
                                </p>
                            )}

                            {carga.anomalia && carga.motivo_anomalia && (
                                <p className="mt-1 flex items-center gap-1 text-xs text-red-600">
                                    <AlertTriangle className="size-3.5 shrink-0" />
                                    {carga.motivo_anomalia}
                                </p>
                            )}
                        </div>
                    </div>

                    {puedeGestionar && (
                        <div className="flex shrink-0 items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onProcesar(carga)}
                            >
                                <Pencil className="size-4" />
                                {carga.procesada ? 'Editar' : 'Procesar'}
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-9 text-destructive hover:text-destructive"
                                title="Eliminar"
                                onClick={() => onEliminar(carga)}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

function Fotos({ carga }: { carga: CargaCombustible }) {
    return (
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

function EstadoBadge({ carga }: { carga: CargaCombustible }) {
    if (!carga.procesada) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-600/20">
                <Clock className="size-3" />
                Por procesar
            </span>
        );
    }

    if (carga.anomalia) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-700 ring-1 ring-red-600/20">
                <AlertTriangle className="size-3" />
                Revisar
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/20">
            Procesada
        </span>
    );
}
