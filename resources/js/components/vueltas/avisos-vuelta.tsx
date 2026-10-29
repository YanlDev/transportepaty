import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { IconoWhatsapp } from '@/components/icono-whatsapp';
import { Button } from '@/components/ui/button';
import { EstadoEnvio } from '@/components/whatsapp/estado-envio';
import type { AvisoVuelta } from '@/types/vueltas';

/**
 * Los avisos por WhatsApp de una vuelta: al conductor (o su alterno) y a
 * cada área. Con el número de la empresa vinculado se mandan por la cola y
 * se ve hasta dónde llegaron (✓✓); si no, se abre WhatsApp con el texto.
 */
export function AvisosVuelta({
    salida,
    conectado,
    puedeAvisar,
}: {
    salida: { id: number; aviso: AvisoVuelta };
    conectado: boolean;
    puedeAvisar: boolean;
}) {
    const [verMensaje, setVerMensaje] = useState(false);
    const { aviso } = salida;

    const enviar = (url: string, datos: Record<string, string> = {}) =>
        router.post(url, datos, { preserveScroll: true });

    const abrirWhatsapp = (numero: string, mensaje: string) =>
        window.open(
            `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`,
            '_blank',
            'noopener',
        );

    if (!puedeAvisar) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-t pt-3">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-medium text-muted-foreground">
                    Avisar:
                </span>
                {aviso.destinatarios.length === 0 && (
                    <span className="text-xs text-muted-foreground">
                        el conductor no tiene celular cargado
                    </span>
                )}
                {aviso.destinatarios.map((destinatario) => (
                    <Button
                        key={destinatario.numero}
                        variant="outline"
                        size="sm"
                        className="h-7"
                        onClick={() =>
                            conectado
                                ? enviar(
                                      vueltas.avisarConductor(salida.id).url,
                                      {
                                          numero: destinatario.numero,
                                          destino: destinatario.etiqueta,
                                      },
                                  )
                                : abrirWhatsapp(
                                      destinatario.numero,
                                      aviso.mensaje,
                                  )
                        }
                    >
                        <IconoWhatsapp className="size-3.5" />
                        {destinatario.etiqueta}
                    </Button>
                ))}
                <EstadoEnvio envio={aviso.envios.conductor} />
            </div>

            {aviso.areas.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {aviso.areas.map((area) => (
                        <span key={area.id} className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7"
                                onClick={() =>
                                    conectado
                                        ? enviar(
                                              vueltas.avisarArea({
                                                  vuelta: salida.id,
                                                  area: area.id,
                                              }).url,
                                          )
                                        : abrirWhatsapp(
                                              area.numero,
                                              area.mensaje,
                                          )
                                }
                            >
                                <IconoWhatsapp className="size-3.5" />
                                {area.area}
                            </Button>
                            <EstadoEnvio envio={aviso.envios.areas[area.id]} />
                        </span>
                    ))}
                </div>
            )}

            <button
                type="button"
                onClick={() => setVerMensaje(!verMensaje)}
                className="flex items-center gap-1 self-start text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronDown
                    className={`size-3.5 transition ${verMensaje ? 'rotate-180' : ''}`}
                />
                {verMensaje ? 'Ocultar el mensaje' : 'Ver el mensaje'}
            </button>
            {verMensaje && (
                <pre className="rounded-md bg-muted p-3 text-xs whitespace-pre-wrap">
                    {aviso.mensaje}
                </pre>
            )}
        </div>
    );
}
