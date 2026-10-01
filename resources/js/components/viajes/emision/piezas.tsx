import {
    AlertTriangle,
    CheckCircle2,
    FileText,
    History,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { registrar as registrarGr } from '@/actions/App/Http/Controllers/EmisionGreController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { tokenXsrf } from '@/components/viajes/emision/http';
import type {
    Consulta,
    GuiaRemitente,
    Mtc,
    ResultadoEmision,
} from '@/components/viajes/emision/tipos';
import { EnviarGrWhatsapp } from '@/components/viajes/enviar-gr-whatsapp';

/**
 * Las piezas de la pantalla «Emitir GR»: cada sección, la tarjeta de una
 * GR-remitente, el TUCE de una placa y el resultado de la emisión.
 */

export function ResultadoDeEmision({
    resultado,
    detalle = [],
}: {
    resultado: ResultadoEmision;
    /** Fecha, placas, conductor… para el mensaje de WhatsApp. */
    detalle?: string[];
}) {
    const [registrando, setRegistrando] = useState(false);
    const [registrado, setRegistrado] = useState(
        resultado.estado === 'emitida' && resultado.viajeRegistrado,
    );
    const [errorRegistro, setErrorRegistro] = useState<string | null>(null);
    const [pdfUrl, setPdfUrl] = useState<string | null>(
        resultado.estado === 'emitida' ? resultado.pdfUrl : null,
    );

    const registrarViaje = async (numeroGr: string) => {
        setRegistrando(true);
        setErrorRegistro(null);

        try {
            const respuesta = await fetch(registrarGr.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': tokenXsrf(),
                },
                body: JSON.stringify({ numero_gr: numeroGr }),
            });
            const cuerpo = await respuesta.json().catch(() => ({}));

            if (respuesta.ok && cuerpo.viajeRegistrado) {
                setRegistrado(true);
                setPdfUrl(cuerpo.pdfUrl ?? null);
            } else {
                setErrorRegistro(
                    cuerpo.mensaje ??
                        'SUNAT todavía no entrega el PDF. Prueba en un minuto.',
                );
            }
        } catch {
            setErrorRegistro('No se pudo conectar. Prueba en un minuto.');
        } finally {
            setRegistrando(false);
        }
    };

    if (resultado.estado === 'emitida') {
        return (
            <Alert className="mt-4 border-emerald-600/40">
                <CheckCircle2 className="size-4 text-emerald-600" />
                <AlertTitle>Emitida {resultado.numeroGr}</AlertTitle>
                <AlertDescription>
                    {registrado
                        ? 'El viaje ya está registrado en Viajes con el PDF de SUNAT.'
                        : 'SUNAT la emitió, pero todavía no entregó el PDF, así que el viaje no se registró.'}{' '}
                    <a
                        className="underline"
                        href={viajes.index.url({
                            query: { buscar: resultado.numeroGr },
                        })}
                    >
                        Ver en Viajes
                    </a>
                    {/* Se queda a la vista mientras se esté en la pantalla:
                        si desapareciera sola, una distracción obligaría a ir
                        a buscar la GR a Viajes para mandarla. */}
                    <EnviarGrWhatsapp
                        className="mt-3 w-full sm:w-auto"
                        numeroGr={resultado.numeroGr}
                        pdfUrl={pdfUrl}
                        detalle={detalle}
                    />
                    {!registrado && (
                        <div className="mt-2 flex flex-col items-start gap-1">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={registrando}
                                onClick={() =>
                                    registrarViaje(resultado.numeroGr)
                                }
                            >
                                {registrando && <Spinner />}
                                Registrar el viaje
                            </Button>
                            {errorRegistro && (
                                <span className="text-xs text-destructive">
                                    {errorRegistro}
                                </span>
                            )}
                        </div>
                    )}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert variant="destructive" className="mt-4">
            <AlertTriangle className="size-4" />
            <AlertTitle>
                {resultado.estado === 'en_duda'
                    ? 'No se sabe si se emitió'
                    : 'No se emitió'}
            </AlertTitle>
            <AlertDescription>{resultado.mensaje}</AlertDescription>
        </Alert>
    );
}

export function Seccion({
    titulo,
    descripcion,
    children,
}: {
    titulo: string;
    descripcion?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-4 sm:p-5">
            <div className="mb-4">
                <h2 className="text-sm font-semibold text-foreground">
                    {titulo}
                </h2>
                {descripcion && (
                    <p className="text-xs text-muted-foreground">
                        {descripcion}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export function TarjetaGuia({
    guia,
    onQuitar,
}: {
    guia: GuiaRemitente;
    onQuitar: () => void;
}) {
    return (
        <li className="rounded-lg border border-border p-3 text-sm">
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2 font-medium">
                    <FileText className="size-4 text-muted-foreground" />
                    {guia.serie}-{guia.numero}
                    {!guia.completa && (
                        <span className="text-xs font-normal text-amber-600">
                            (resumida)
                        </span>
                    )}
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Quitar GR-remitente"
                    onClick={onQuitar}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
            <dl className="mt-1 grid gap-1 text-muted-foreground">
                <div>
                    <dt className="sr-only">Remitente y destinatario</dt>
                    <dd>
                        {guia.remitente ?? '—'} → {guia.destinatario ?? '—'}
                    </dd>
                </div>
                <div>
                    <dt className="sr-only">Ruta</dt>
                    <dd>
                        {guia.partida ?? '—'}
                        <br />→ {guia.llegada ?? '—'}
                    </dd>
                </div>
                <div>
                    <dt className="sr-only">Carga</dt>
                    <dd>
                        {guia.peso ?? '—'} {guia.unidadPeso ?? ''}
                        {guia.bultos ? ` · ${guia.bultos} bultos` : ''}
                        {guia.fechaTraslado
                            ? ` · traslado ${guia.fechaTraslado}`
                            : ''}
                    </dd>
                </div>
            </dl>
            {guia.avisos.length > 0 && (
                <ul className="mt-2 grid gap-1">
                    {guia.avisos.map((aviso) => (
                        <li
                            key={aviso}
                            className="flex items-start gap-1 text-destructive"
                        >
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            {aviso}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

/** Avisa que el campo lo completó el último viaje, no la persona. */
export function NotaUltimoViaje() {
    return (
        <p className="flex items-center gap-1 text-xs text-muted-foreground">
            <History className="size-3.5" />
            Del último viaje; cámbialo si esta vez es otro.
        </p>
    );
}

export function TuceDeVehiculo({
    consulta,
    elegido,
    rucPaty,
    onCambio,
}: {
    consulta?: Consulta<Mtc>;
    elegido?: string;
    rucPaty: string;
    onCambio: (valor: string) => void;
}) {
    if (!consulta || consulta.estado === 'cargando') {
        return (
            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                <Spinner /> Buscando el TUCE…
            </p>
        );
    }

    if (consulta.estado === 'error') {
        return <p className="text-xs text-destructive">{consulta.mensaje}</p>;
    }

    const { numero, origen, vence, placaEnSunat } = consulta.datos;
    const valor = elegido ?? numero;
    const esRuc = valor === rucPaty;

    return (
        <div className="grid gap-1">
            <div className="flex gap-2">
                <Input
                    aria-label="TUCE o certificado de habilitación"
                    className="h-8 text-xs"
                    value={valor}
                    onChange={(e) => onCambio(e.target.value.toUpperCase())}
                />
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-8 shrink-0 text-xs"
                    disabled={esRuc}
                    onClick={() => onCambio(rucPaty)}
                >
                    Usar RUC de Paty
                </Button>
            </div>
            <p
                className={
                    esRuc
                        ? 'text-xs text-amber-600'
                        : 'flex flex-wrap items-center gap-1 text-xs text-emerald-600'
                }
            >
                {esRuc ? (
                    'Va el RUC de Paty en lugar del TUCE.'
                ) : elegido !== undefined && elegido !== numero ? (
                    <span className="text-muted-foreground">
                        TUCE escrito a mano.
                    </span>
                ) : (
                    <>
                        <CheckCircle2 className="size-3.5" />
                        {origen === 'transpaty'
                            ? `De la ficha${vence ? `, vence ${vence}` : ''}`
                            : 'Del MTC'}
                    </>
                )}
                {!placaEnSunat && (
                    <span className="text-muted-foreground">
                        {' '}
                        (SUNAT aún no tiene registrada la placa)
                    </span>
                )}
            </p>
        </div>
    );
}
