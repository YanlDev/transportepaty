import { Check, Checks, Clock, WarningCircle } from '@phosphor-icons/react';
import { cn } from '@/lib/utils';
import type { ResumenEnvio } from '@/types/programacion';

/**
 * Los mismos ✓ del chat de WhatsApp, junto al botón que mandó el aviso:
 * enviado, entregado al celular o leído. Al pasar el mouse dice a quién,
 * cuándo y, si falló, por qué.
 */
export function EstadoEnvio({ envio }: { envio: ResumenEnvio | null | undefined }) {
    if (!envio) {
        return null;
    }

    const hora = new Date(envio.hora).toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    const titulo =
        envio.estado === 'fallido'
            ? `No se pudo enviar a ${envio.destino}: ${envio.error ?? 'error desconocido'}`
            : `${envio.estado_label} · ${envio.destino} · ${hora}`;

    const icono = {
        pendiente: <Clock className="size-3.5 animate-pulse" />,
        enviado: <Check weight="bold" className="size-3.5" />,
        entregado: <Checks weight="bold" className="size-3.5" />,
        leido: <Checks weight="bold" className="size-3.5" />,
        fallido: <WarningCircle weight="fill" className="size-3.5" />,
    }[envio.estado];

    return (
        <span
            title={titulo}
            aria-label={titulo}
            className={cn(
                'inline-flex items-center',
                envio.estado === 'leido' && 'text-sky-500',
                envio.estado === 'fallido' && 'text-destructive',
                ['pendiente', 'enviado', 'entregado'].includes(envio.estado) &&
                    'text-muted-foreground',
            )}
        >
            {icono}
        </span>
    );
}
