import { router, usePage } from '@inertiajs/react';
import {
    CaretDown,
    Headset,
    Package,
    Phone,
    Receipt,
    Warning,
    WhatsappLogo,
} from '@phosphor-icons/react';
import { useState } from 'react';
import avisoSalida from '@/actions/App/Http/Controllers/AvisoSalidaController';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import { EnviarAvisoDialog } from '@/components/programacion/enviar-aviso-dialog';
import type { EnvioPendiente } from '@/components/programacion/enviar-aviso-dialog';
import { NumerosDialog } from '@/components/programacion/numeros-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { avisarError } from '@/lib/aviso-error';
import { cn } from '@/lib/utils';
import type {
    AvisoDeArea,
    DestinatarioAviso,
    ProgramacionTarjeta,
    TipoAvisoSalida,
} from '@/types/programacion';

/**
 * Abre WhatsApp con el mensaje ya escrito. `wa.me` lo entiende tanto la app
 * del celular como WhatsApp Web, así que sirve desde la oficina y desde el
 * teléfono sin preguntar dónde se está.
 *
 * Solo texto: un enlace de WhatsApp no puede llevar adjuntos, y por eso la
 * advertencia va escrita y no como imagen.
 */
export function abrirWhatsapp(numero: string, mensaje: string): void {
    window.open(
        `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`,
        '_blank',
        'noopener',
    );
}

/**
 * Los dos mensajes que se le mandan al conductor antes de salir: el aviso de
 * su programación y la advertencia de documentación.
 *
 * Van separados porque WhatsApp esconde detrás de «ver más» todo lo que pase
 * de unas líneas, y la advertencia es justamente lo que no puede quedar
 * escondido. Cualquiera de los dos deja registrado que se avisó, con la hora
 * y quién lo mandó: es lo que respalda a la empresa si la unidad sale igual.
 *
 * El botón «Avisar» abre el menú con los números del conductor —el suyo, su
 * alterno, el adicional de esta salida—. Abastecimiento y facturación tienen
 * cada uno su botón a la vista, con su propio texto: abastecimiento, qué
 * unidad sale y a dónde; facturación, además, el flete acordado. Solo lo que
 * se le manda al conductor marca la salida como avisada.
 */
export function AvisoSalida({
    tarjeta,
    advertencia,
    editable,
}: {
    tarjeta: ProgramacionTarjeta;
    advertencia: string;
    editable: boolean;
}) {
    const avisado = tarjeta.aviso_enviado_at !== null;
    const destinatarios = tarjeta.destinatarios;
    const [editandoNumeros, setEditandoNumeros] = useState(false);
    const [envio, setEnvio] = useState<EnvioPendiente | null>(null);

    // Con el número de la empresa vinculado, el aviso sale como imagen desde
    // la app, previa vista; si no, se abre WhatsApp con el texto, como antes.
    const { whatsappConectado } = usePage<{ whatsappConectado?: boolean }>()
        .props;

    const dialogoEnvio = (
        <EnviarAvisoDialog envio={envio} onCerrar={() => setEnvio(null)} />
    );

    // Las áreas no dependen del teléfono del conductor: sus botones se
    // muestran aunque a él todavía no se le pueda avisar.
    const botonesArea = tarjeta.avisos_area.map((aviso) => (
        <BotonArea
            key={aviso.area}
            aviso={aviso}
            onAvisar={() =>
                whatsappConectado
                    ? setEnvio({
                          etiqueta: aviso.area,
                          numero: aviso.numero,
                          imagenUrl: avisoSalida.imagenArea([
                              tarjeta.id,
                              aviso.id,
                          ]).url,
                          enviarUrl: avisoSalida.enviarArea([
                              tarjeta.id,
                              aviso.id,
                          ]).url,
                      })
                    : abrirWhatsapp(aviso.numero, aviso.mensaje)
            }
        />
    ));

    const mandar = (
        destinatario: DestinatarioAviso,
        mensaje: string,
        tipo: TipoAvisoSalida,
    ) => {
        if (whatsappConectado) {
            setEnvio({
                etiqueta: destinatario.etiqueta,
                numero: destinatario.numero,
                imagenUrl: avisoSalida.imagen([tarjeta.id, tipo]).url,
                enviarUrl: avisoSalida.enviar([tarjeta.id, tipo]).url,
            });

            return;
        }

        abrirWhatsapp(destinatario.numero, mensaje);

        router.post(
            programacion.registrarAviso(tarjeta.id).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: avisarError,
            },
        );
    };

    // Sin número no hay a quién avisar, así que lo único que ofrece la celda
    // es cargarlo.
    if (destinatarios.length === 0) {
        return editable ? (
            <div className="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    onClick={() => setEditandoNumeros(true)}
                    className="flex items-center gap-1.5 rounded-md border border-dashed px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    title={`${tarjeta.conductor} no tiene teléfono registrado`}
                >
                    <Phone className="size-3.5" />
                    Cargar número
                </button>
                {botonesArea}
                {dialogoEnvio}
                {editandoNumeros && (
                    <NumerosDialog
                        tarjeta={tarjeta}
                        open
                        onOpenChange={setEditandoNumeros}
                    />
                )}
            </div>
        ) : (
            <p className="text-xs text-muted-foreground">Sin teléfono</p>
        );
    }

    // Para quien solo mira el tablero: el aviso se ve, pero no se toca.
    if (!editable) {
        return (
            <p className="text-xs text-muted-foreground">
                {avisado ? `Avisado ${horaDelAviso(tarjeta)}` : 'Sin avisar'}
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <DropdownMenu>
                <DropdownMenuTrigger
                    title={
                        avisado
                            ? `Avisado ${horaDelAviso(tarjeta)}${tarjeta.aviso_enviado_por ? ` por ${tarjeta.aviso_enviado_por}` : ''}. Volver a enviar.`
                            : 'Mandar el aviso de esta salida'
                    }
                    className={cn(
                        'flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium transition-colors',
                        avisado
                            ? 'text-muted-foreground hover:bg-accent'
                            : 'border-emerald-600/30 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900',
                    )}
                >
                    <WhatsappLogo weight="fill" className="size-3.5" />
                    {avisado ? `Avisado ${horaDelAviso(tarjeta)}` : 'Avisar'}
                    <CaretDown className="size-3" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuLabel>Conductor</DropdownMenuLabel>
                    {destinatarios.map((destinatario) => (
                        <DropdownMenuItem
                            key={destinatario.numero}
                            onSelect={() =>
                                mandar(
                                    destinatario,
                                    tarjeta.mensaje_aviso,
                                    'conductor',
                                )
                            }
                        >
                            {destinatario.etiqueta}
                            <span className="ml-2 font-mono text-xs text-muted-foreground">
                                {destinatario.numero}
                            </span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            <BotonWhatsapp
                etiqueta="Advertencia"
                titulo="Mandar la advertencia de documentación"
                icono={<Warning weight="fill" className="size-3.5" />}
                destacado={false}
                destinatarios={destinatarios}
                onElegir={(destinatario) =>
                    mandar(destinatario, advertencia, 'advertencia')
                }
            />

            {/* Las áreas de la casa reciben su propio texto y no marcan la
                salida como avisada: lo que respalda ante una multa es
                habérselo dicho al conductor. */}
            {botonesArea}
            {dialogoEnvio}

            <button
                type="button"
                onClick={() => setEditandoNumeros(true)}
                title="Números para avisar"
                aria-label={`Números para avisar a ${tarjeta.conductor}`}
                className="rounded-md border border-transparent p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                <Phone className="size-3.5" />
            </button>

            {/* Montado solo al abrirlo: así el formulario arranca con los
                números que están guardados ahora, y no con los que había
                cuando se dibujó la tabla. */}
            {editandoNumeros && (
                <NumerosDialog
                    tarjeta={tarjeta}
                    open
                    onOpenChange={setEditandoNumeros}
                />
            )}
        </div>
    );
}

/**
 * Un botón que manda a WhatsApp. Con un solo número manda directo; con varios
 * abre el menú, porque preguntar a cuál chat ir cuando hay uno solo es una
 * pregunta de más.
 */
function BotonWhatsapp({
    etiqueta,
    titulo,
    icono,
    destacado,
    destinatarios,
    onElegir,
}: {
    etiqueta: string;
    titulo: string;
    icono: React.ReactNode;
    destacado: boolean;
    destinatarios: DestinatarioAviso[];
    onElegir: (destinatario: DestinatarioAviso) => void;
}) {
    const clases = cn(
        'flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium transition-colors',
        destacado
            ? 'border-emerald-600/30 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900'
            : 'text-muted-foreground hover:bg-accent',
    );

    if (destinatarios.length === 1) {
        return (
            <button
                type="button"
                onClick={() => onElegir(destinatarios[0])}
                title={titulo}
                className={clases}
            >
                {icono}
                {etiqueta}
            </button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger title={titulo} className={clases}>
                {icono}
                {etiqueta}
                <CaretDown className="size-3" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {destinatarios.map((destinatario) => (
                    <DropdownMenuItem
                        key={destinatario.numero}
                        onSelect={() => onElegir(destinatario)}
                    >
                        {destinatario.etiqueta}
                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                            {destinatario.numero}
                        </span>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

const ICONOS_AREA: Record<string, React.ReactNode> = {
    Abastecimiento: <Package weight="fill" className="size-3.5" />,
    Facturación: <Receipt weight="fill" className="size-3.5" />,
    'Centro de Control': <Headset weight="fill" className="size-3.5" />,
};

/** Avisa al área de esta salida, como imagen o con el texto en WhatsApp. */
function BotonArea({
    aviso,
    onAvisar,
}: {
    aviso: AvisoDeArea;
    onAvisar: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onAvisar}
            title={`Mandar el aviso a ${aviso.area} (${aviso.numero})`}
            className="flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
            {ICONOS_AREA[aviso.area] ?? (
                <WhatsappLogo weight="fill" className="size-3.5" />
            )}
            {aviso.area}
        </button>
    );
}

/** `07:12`, en la hora del navegador, que es la de la operación. */
function horaDelAviso(tarjeta: ProgramacionTarjeta): string {
    if (tarjeta.aviso_enviado_at === null) {
        return '';
    }

    return new Date(tarjeta.aviso_enviado_at).toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}
