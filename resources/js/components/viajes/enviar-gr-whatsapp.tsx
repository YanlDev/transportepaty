import { IconoWhatsapp } from '@/components/icono-whatsapp';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ViajeListItem } from '@/types/fleet';

type Props = {
    numeroGr: string;
    /** Enlace público al PDF de la GR; sin él no hay nada que mandar. */
    pdfUrl: string | null;
    /** Líneas de contexto: fecha, placas, conductor, cliente. */
    detalle: (string | null | undefined)[];
    className?: string;
};

/**
 * Manda la GR por WhatsApp eligiendo el contacto: `wa.me` sin número abre
 * WhatsApp en la lista de chats, con el mensaje ya escrito. Lo que llega es
 * el texto con el enlace al PDF —wa.me no adjunta archivos—, que se abre sin
 * iniciar sesión en Transpaty.
 */
export function EnviarGrWhatsapp({
    numeroGr,
    pdfUrl,
    detalle,
    className,
}: Props) {
    if (!pdfUrl) {
        return null;
    }

    const mensaje = [
        `*GR ${numeroGr}*`,
        ...detalle.filter(Boolean),
        '',
        `PDF: ${pdfUrl}`,
    ].join('\n');

    return (
        <a
            href={`https://wa.me/?text=${encodeURIComponent(mensaje)}`}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
                // El mismo verde del «Enviar por WhatsApp» de Programación.
                'inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus-visible:ring-[3px] focus-visible:ring-emerald-600/40 focus-visible:outline-none pointer-coarse:h-11',
                className,
            )}
        >
            <IconoWhatsapp className="size-4" />
            Enviar por WhatsApp
        </a>
    );
}

/** Las líneas del mensaje para un viaje ya registrado. */
export function detalleParaWhatsapp(viaje: ViajeListItem): string[] {
    return [
        `${formatearFecha(viaje.fecha_traslado)} · ${viaje.origen_ciudad} → ${viaje.destino_ciudad}`,
        `${formatearPlaca(viaje.placa_tracto)}${viaje.placa_carreta ? ` / ${formatearPlaca(viaje.placa_carreta)}` : ''} · ${viaje.conductor_nombre}`,
        viaje.cliente,
    ];
}
