import { router } from '@inertiajs/react';
import { FileText, Trash2 } from 'lucide-react';
import { destroy } from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
import { formatearFecha } from '@/lib/format';
import type { VehiculoDocumentoItem } from '@/types/fleet';

type Props = {
    documento: VehiculoDocumentoItem;
    vehiculoId: number;
    puedeGestionar: boolean;
};

export function DocumentoItem({
    documento,
    vehiculoId,
    puedeGestionar,
}: Props) {
    return (
        <div className="flex items-center gap-2 rounded-lg border border-border p-3">
            <a
                href={documento.url}
                target="_blank"
                rel="noreferrer"
                className="flex min-w-0 flex-1 items-center gap-3"
            >
                <div className="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700">
                    <FileText className="size-4" />
                </div>
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-foreground">
                        {documento.tipo_label}
                        {documento.numero && (
                            <span className="text-muted-foreground">
                                {' · '}
                                {documento.numero}
                            </span>
                        )}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {documento.fecha_vencimiento
                            ? `Vence: ${formatearFecha(documento.fecha_vencimiento)}`
                            : 'Sin vencimiento'}
                    </p>
                </div>
            </a>

            <DocumentoEstadoBadge fecha={documento.fecha_vencimiento} />

            {puedeGestionar && (
                <button
                    type="button"
                    onClick={() =>
                        router.delete(destroy([vehiculoId, documento.id]).url, {
                            preserveScroll: true,
                        })
                    }
                    className="shrink-0 text-muted-foreground transition-colors hover:text-destructive"
                    aria-label="Eliminar documento"
                >
                    <Trash2 className="size-4" />
                </button>
            )}
        </div>
    );
}

function DocumentoEstadoBadge({ fecha }: { fecha: string | null }) {
    let clase = 'bg-zinc-100 text-zinc-600';
    let etiqueta = 'Sin vencimiento';

    if (fecha) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const vence = new Date(`${fecha}T00:00:00`);
        const dias = Math.round(
            (vence.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24),
        );

        if (dias < 0) {
            clase = 'bg-red-50 text-red-700';
            etiqueta = 'Vencido';
        } else if (dias <= 30) {
            clase = 'bg-amber-100 text-amber-700';
            etiqueta = 'Próximo a vencer';
        } else {
            clase = 'bg-emerald-50 text-emerald-700';
            etiqueta = 'Vigente';
        }
    }

    return (
        <span
            className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${clase}`}
        >
            {etiqueta}
        </span>
    );
}
