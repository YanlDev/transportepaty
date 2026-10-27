import { Wrench } from 'lucide-react';
import { useState } from 'react';
import {
    EstadoTallerBadge,
    fechaHoraTaller,
} from '@/components/taller/estado-taller';
import { RevisionTallerDialog } from '@/components/taller/revision-taller-dialog';
import { Button } from '@/components/ui/button';
import type { RevisionTaller } from '@/types/vueltas';

function Revision({ revision }: { revision: RevisionTaller }) {
    return (
        <div className="flex flex-col gap-0.5 text-sm">
            {revision.motivo && <span>{revision.motivo}</span>}
            {revision.trabajos && (
                <span className="whitespace-pre-line text-muted-foreground">
                    {revision.resultado === 'apto'
                        ? 'Trabajos realizados: '
                        : 'Trabajos a realizar: '}
                    {revision.trabajos}
                </span>
            )}
            <span className="text-xs text-muted-foreground">
                {revision.resultado_label} · {revision.por ?? '—'} ·{' '}
                {fechaHoraTaller(revision.en)}
            </span>
        </div>
    );
}

/**
 * El paso de taller dentro del ticket: en qué quedó, el historial (no apto,
 * después apto) y el botón para revisarlo.
 */
export function TallerVuelta({
    ticket,
}: {
    ticket: {
        id: number;
        codigo: string | null;
        placa: string;
        taller: RevisionTaller | null;
        taller_anteriores: RevisionTaller[];
        revisable: boolean;
    };
}) {
    const [revisando, setRevisando] = useState(false);

    return (
        <section className="flex flex-col gap-2 rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-sm font-semibold">
                    <Wrench className="size-4 text-muted-foreground" />
                    Taller
                </h3>
                <EstadoTallerBadge revision={ticket.taller} />
            </div>

            {ticket.taller ? (
                <Revision revision={ticket.taller} />
            ) : (
                <p className="text-sm text-muted-foreground">
                    Taller todavía no dio el OK: abastecimiento no puede
                    registrar nada.
                </p>
            )}

            {ticket.taller_anteriores.length > 0 && (
                <details className="text-sm">
                    <summary className="cursor-pointer text-xs text-muted-foreground">
                        Revisiones anteriores ({ticket.taller_anteriores.length}
                        )
                    </summary>
                    <div className="mt-2 flex flex-col gap-2 border-l pl-3">
                        {ticket.taller_anteriores.map((revision) => (
                            <Revision key={revision.en} revision={revision} />
                        ))}
                    </div>
                </details>
            )}

            {ticket.revisable && (
                <Button
                    variant={
                        ticket.taller?.resultado === 'apto'
                            ? 'outline'
                            : 'default'
                    }
                    size="sm"
                    className="self-start"
                    onClick={() => setRevisando(true)}
                >
                    {ticket.taller === null
                        ? 'Dar apto / no apto'
                        : ticket.taller.resultado === 'no_apto'
                          ? 'Actualizar (ya está lista / cambia la fecha)'
                          : 'Volver a revisar'}
                </Button>
            )}

            <RevisionTallerDialog
                ticket={revisando ? ticket : null}
                onCerrar={() => setRevisando(false)}
            />
        </section>
    );
}
