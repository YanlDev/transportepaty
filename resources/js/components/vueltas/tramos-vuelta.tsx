import { ArrowDownRight, ArrowUpRight } from 'lucide-react';
import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EstadoVuelta, ParadaVuelta, Vuelta } from '@/types/vueltas';

const TONO_ESTADO: Record<EstadoVuelta, StatusTone> = {
    programada: 'info',
    en_curso: 'warning',
    cerrada: 'neutral',
    anulada: 'danger',
};

export function EstadoVueltaBadge({ vuelta }: { vuelta: Vuelta }) {
    return (
        <StatusBadge
            label={vuelta.estado_label}
            tone={TONO_ESTADO[vuelta.estado]}
        />
    );
}

/**
 * Una parada: de dónde a dónde, con qué carga y para quién. El vacío va
 * apagado: recorre la ruta pero no lleva GR.
 */
function Parada({
    parada,
    conGuias,
}: {
    parada: ParadaVuelta;
    conGuias: boolean;
}) {
    const vacio = parada.tipo_carga === 'vacio';

    return (
        <li
            className={cn(
                'flex flex-col gap-1 rounded-md border px-3 py-2 text-sm',
                (vacio || parada.pendiente) &&
                    'border-dashed text-muted-foreground',
            )}
        >
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="font-medium">
                    {parada.origen ?? '¿?'} → {parada.destino ?? '¿?'}
                </span>
                {vacio ? (
                    <span className="text-xs italic">vacío</span>
                ) : (
                    <TipoCargaBadge
                        valor={parada.tipo_carga}
                        label={parada.tipo_carga_label}
                    />
                )}
                {parada.fecha && (
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {formatearFecha(parada.fecha)}
                    </span>
                )}
                {parada.pendiente && (
                    <StatusBadge label="Sin GR aún" tone="info" />
                )}
                {parada.fuera_de_programa && (
                    <StatusBadge label="No programada" tone="warning" />
                )}
            </div>
            {parada.cliente && (
                <span className="truncate text-xs text-muted-foreground">
                    {parada.cliente}
                </span>
            )}
            {conGuias && parada.guias.length > 0 && (
                <span className="font-mono text-xs text-muted-foreground">
                    {parada.guias.map((guia) => guia.numero_gr).join(' · ')}
                </span>
            )}
        </li>
    );
}

function Tramo({
    titulo,
    subida,
    paradas,
    conGuias,
}: {
    titulo: string;
    subida: boolean;
    paradas: ParadaVuelta[];
    conGuias: boolean;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2">
            <h3 className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {subida ? (
                    <ArrowUpRight className="size-3.5" />
                ) : (
                    <ArrowDownRight className="size-3.5" />
                )}
                {titulo}
            </h3>
            {paradas.length === 0 ? (
                <p className="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground">
                    Sin registro
                </p>
            ) : (
                <ol className="flex flex-col gap-2">
                    {paradas.map((parada) => (
                        <Parada
                            key={parada.id}
                            parada={parada}
                            conGuias={conGuias}
                        />
                    ))}
                </ol>
            )}
        </div>
    );
}

/**
 * Los dos tramos de la vuelta lado a lado (uno sobre otro en el celular):
 * la ida sube a la sierra, el regreso baja a la costa.
 */
export function TramosVuelta({
    vuelta,
    conGuias = false,
}: {
    vuelta: Vuelta;
    conGuias?: boolean;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <Tramo
                titulo="Ida · subida"
                subida
                paradas={vuelta.ida}
                conGuias={conGuias}
            />
            <Tramo
                titulo="Regreso · bajada"
                subida={false}
                paradas={vuelta.regreso}
                conGuias={conGuias}
            />
        </div>
    );
}
