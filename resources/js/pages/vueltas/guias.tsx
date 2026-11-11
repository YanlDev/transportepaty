import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { FileStack, Undo2, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import guiasRutas from '@/actions/App/Http/Controllers/GuiaPorAsignarController';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Placa } from '@/components/placa';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Field } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AdicionalDialog } from '@/components/vueltas/adicionales-vuelta';
import { formatearFecha } from '@/lib/format';

type Sentido = 'ida' | 'regreso';

type DatosGuia = {
    id: number;
    numero_gr: string;
    fecha: string;
    placa: string;
    tiene_unidad: boolean;
    conductor: string | null;
    origen: string | null;
    destino: string | null;
    carga: string;
    peso: string;
    cliente: string;
};

type TicketAbierto = {
    id: number;
    codigo: string | null;
    inicio: string | null;
    estado_label: string;
    paradas: {
        id: number;
        sentido: Sentido;
        texto: string;
        carga: string;
        guias: number;
        no_programada: boolean;
        /** Iba vacía en ese trayecto: esta GR la reemplaza. */
        vacio: boolean;
    }[];
};

type Pendiente = DatosGuia & {
    tickets: TicketAbierto[];
    sugerencia: {
        vuelta_id: number | null;
        parada_id: number | null;
        sentido: Sentido;
    };
};

type SinTicket = DatosGuia & {
    motivo: string | null;
    por: string | null;
    en: string | null;
};

/** Lo que avisa el servidor al asignar una carga fuera del programa. */
type CargaSinProgramar = {
    vuelta_id: number;
    codigo: string | null;
    sentido: Sentido;
    numero_gr: string;
    trayecto: string;
};

type Props = {
    pendientes: Pendiente[];
    sinTicket: SinTicket[];
    puedeAsignar: boolean;
};

/** El valor del selector de parada para una que no estaba programada. */
const nueva = (sentido: Sentido) => `nueva-${sentido}`;

const nombreTramo = (sentido: Sentido) =>
    sentido === 'ida' ? 'Ida' : 'Vuelta';

/**
 * Una GR de la bandeja: el sistema propone el ticket abierto de la unidad y
 * la parada que coincide; quien asigna confirma o elige otra. Si no calza con
 * nada de lo programado, entra como parada no programada en el tramo que se
 * elija.
 */
function FilaGuia({
    guia,
    puedeAsignar,
    onSinTicket,
}: {
    guia: Pendiente;
    puedeAsignar: boolean;
    onSinTicket: (guia: Pendiente) => void;
}) {
    const paradaSugerida =
        guia.sugerencia.parada_id !== null
            ? String(guia.sugerencia.parada_id)
            : nueva(guia.sugerencia.sentido);
    const { data, setData, transform, post, processing, errors } = useForm({
        vuelta_id: guia.sugerencia.vuelta_id,
        parada: guia.sugerencia.vuelta_id !== null ? paradaSugerida : '',
    });
    const ticket = guia.tickets.find((opcion) => opcion.id === data.vuelta_id);
    // Los errores vienen con los nombres que recibe el servidor.
    const errores = errors as Record<string, string | undefined>;

    const asignar = () => {
        transform((datos) => ({
            vuelta_id: datos.vuelta_id,
            parada_vuelta_id: datos.parada.startsWith('nueva-')
                ? null
                : Number(datos.parada),
            sentido: datos.parada.startsWith('nueva-')
                ? datos.parada.replace('nueva-', '')
                : null,
        }));
        post(guiasRutas.asignar(guia.id).url, { preserveScroll: true });
    };

    return (
        <article className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="flex flex-col gap-1">
                    <span className="flex items-center gap-2">
                        <Placa placa={guia.placa} />
                        <span className="font-mono text-sm">
                            {guia.numero_gr}
                        </span>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {formatearFecha(guia.fecha)}
                        </span>
                    </span>
                    <span className="text-sm">
                        {guia.origen ?? '¿?'} → {guia.destino ?? '¿?'}
                        <span className="text-muted-foreground">
                            {' '}
                            · {guia.carga} · {guia.peso}
                        </span>
                    </span>
                    <span className="flex items-center gap-1.5 text-sm font-medium">
                        <UserRound className="size-3.5 shrink-0 text-muted-foreground" />
                        {guia.conductor ?? (
                            <span className="font-normal text-muted-foreground">
                                Sin conductor en la GR
                            </span>
                        )}
                    </span>
                    <span className="max-w-xl truncate text-xs text-muted-foreground">
                        {guia.cliente}
                    </span>
                </div>
                {puedeAsignar && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onSinTicket(guia)}
                    >
                        Sin ticket…
                    </Button>
                )}
            </div>

            {!guia.tiene_unidad ? (
                <p className="text-sm text-amber-700 dark:text-amber-400">
                    La placa de la GR no es de ninguna unidad del padrón:
                    corrígela en Guías antes de asignarla.
                </p>
            ) : guia.tickets.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    La unidad no tiene un ticket abierto.{' '}
                    <Link
                        href={vueltasRutas.index().url}
                        className="underline underline-offset-2"
                    >
                        Prográmalo
                    </Link>{' '}
                    o márcala sin ticket si ya andaba en ruta.
                </p>
            ) : (
                puedeAsignar && (
                    <div className="grid gap-2 md:grid-cols-[12rem_1fr_auto] md:items-end">
                        <Field label="Ticket" error={errores.vuelta_id}>
                            {() => (
                                <Select
                                    value={
                                        data.vuelta_id
                                            ? String(data.vuelta_id)
                                            : undefined
                                    }
                                    onValueChange={(valor) =>
                                        setData((anterior) => ({
                                            ...anterior,
                                            vuelta_id: Number(valor),
                                            parada:
                                                Number(valor) ===
                                                guia.sugerencia.vuelta_id
                                                    ? paradaSugerida
                                                    : nueva(
                                                          guia.sugerencia
                                                              .sentido,
                                                      ),
                                        }))
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Elegir…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {guia.tickets.map((opcion) => (
                                            <SelectItem
                                                key={opcion.id}
                                                value={String(opcion.id)}
                                            >
                                                {opcion.codigo} ·{' '}
                                                {opcion.estado_label}
                                                {opcion.id ===
                                                    guia.sugerencia.vuelta_id &&
                                                    ' (sugerido)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field
                            label="Parada"
                            error={errores.parada_vuelta_id ?? errores.sentido}
                        >
                            {() => (
                                <Select
                                    value={data.parada || undefined}
                                    disabled={ticket === undefined}
                                    onValueChange={(valor) =>
                                        setData('parada', valor)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Elegir…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(['ida', 'regreso'] as const).map(
                                            (sentido) => (
                                                <SelectGroup key={sentido}>
                                                    <SelectLabel>
                                                        {nombreTramo(sentido)}
                                                    </SelectLabel>
                                                    {ticket?.paradas
                                                        .filter(
                                                            (parada) =>
                                                                parada.sentido ===
                                                                sentido,
                                                        )
                                                        .map((parada) => (
                                                            <SelectItem
                                                                key={parada.id}
                                                                value={String(
                                                                    parada.id,
                                                                )}
                                                            >
                                                                {parada.texto} ·{' '}
                                                                {parada.vacio
                                                                    ? 'iba vacía: pasa a llevar esta carga'
                                                                    : parada.carga}
                                                                {parada.guias >
                                                                    0 &&
                                                                    ` · ${parada.guias} GR`}
                                                                {parada.id ===
                                                                    guia
                                                                        .sugerencia
                                                                        .parada_id &&
                                                                    ' (sugerida)'}
                                                            </SelectItem>
                                                        ))}
                                                    <SelectItem
                                                        value={nueva(sentido)}
                                                    >
                                                        Nueva parada no
                                                        programada en la{' '}
                                                        {nombreTramo(
                                                            sentido,
                                                        ).toLowerCase()}
                                                    </SelectItem>
                                                </SelectGroup>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Button
                            onClick={asignar}
                            disabled={
                                processing ||
                                data.vuelta_id === null ||
                                data.parada === ''
                            }
                        >
                            Asignar
                        </Button>
                    </div>
                )
            )}
        </article>
    );
}

/** Marcar una GR sin ticket, con su motivo: sale de la bandeja. */
function SinTicketDialog({
    guia,
    onCerrar,
}: {
    guia: Pendiente;
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        motivo: '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(guiasRutas.sinTicket(guia.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex flex-col overflow-hidden md:max-w-md">
                <DialogHeader>
                    <DialogTitle>{guia.numero_gr} sin ticket</DialogTitle>
                    <DialogDescription>
                        Sale de la bandeja. Queda quién lo decidió y por qué, y
                        se puede devolver.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="sin-ticket"
                    onSubmit={enviar}
                    className="flex flex-col gap-4 px-1"
                >
                    <Field label="Motivo" error={errors.motivo} required>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                maxLength={500}
                                autoFocus
                                value={data.motivo}
                                placeholder="Ej.: ya andaba en ruta antes del 05-11"
                                onChange={(evento) =>
                                    setData('motivo', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </form>
                <DialogFooter>
                    <Button
                        type="submit"
                        form="sin-ticket"
                        disabled={processing}
                    >
                        Marcar sin ticket
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * La bandeja de las GR que todavía no están en ningún ticket. Ninguna entra
 * sola: el sistema sugiere y una persona confirma.
 */
export default function GuiasPorAsignar({
    pendientes,
    sinTicket,
    puedeAsignar,
}: Props) {
    const [marcando, setMarcando] = useState<Pendiente | null>(null);
    const [sinProgramar, setSinProgramar] = useState<CargaSinProgramar | null>(
        null,
    );

    // Al asignar una carga que no estaba programada, el servidor avisa: se
    // ofrece sumar ahí mismo el combustible de su desvío.
    useEffect(
        () =>
            router.on('flash', (evento) => {
                const carga = (evento as CustomEvent).detail?.flash
                    ?.carga_sin_programar as CargaSinProgramar | undefined;

                if (carga) {
                    setSinProgramar(carga);
                }
            }),
        [],
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Programación', href: vueltasRutas.index().url },
            { title: 'GR por asignar', href: guiasRutas.index().url },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="GR por asignar" />

            <EncabezadoPantalla
                titulo="GR por asignar"
                descripcion="Ninguna GR entra sola en un ticket: el sistema sugiere el ticket y la parada, y tú confirmas."
                icono={FileStack}
            />

            {pendientes.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No hay GR por asignar.
                </p>
            ) : (
                <div className="flex flex-col gap-3">
                    {pendientes.map((guia) => (
                        <FilaGuia
                            key={guia.id}
                            guia={guia}
                            puedeAsignar={puedeAsignar}
                            onSinTicket={setMarcando}
                        />
                    ))}
                </div>
            )}

            {sinTicket.length > 0 && (
                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-semibold">
                        Marcadas sin ticket (últimas)
                    </h2>
                    <ul className="flex flex-col divide-y rounded-xl border bg-card">
                        {sinTicket.map((guia) => (
                            <li
                                key={guia.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm"
                            >
                                <span className="flex flex-col">
                                    <span>
                                        <span className="font-mono">
                                            {guia.numero_gr}
                                        </span>{' '}
                                        · {guia.placa} ·{' '}
                                        {formatearFecha(guia.fecha)}
                                        {guia.conductor &&
                                            ` · ${guia.conductor}`}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {guia.motivo}
                                        {guia.por && ` · ${guia.por}`}
                                    </span>
                                </span>
                                {puedeAsignar && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                guiasRutas.devolver(guia.id)
                                                    .url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Undo2 className="size-3.5" />
                                        Devolver a la bandeja
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {sinProgramar && (
                <AdicionalDialog
                    key={`${sinProgramar.vuelta_id}-${sinProgramar.numero_gr}`}
                    vueltaId={sinProgramar.vuelta_id}
                    sentidoInicial={sinProgramar.sentido}
                    motivoInicial={`Carga ${sinProgramar.trayecto} (GR ${sinProgramar.numero_gr})`}
                    titulo="¿Sumar combustible por esta carga?"
                    descripcion={`${sinProgramar.trayecto} no estaba programada en el ticket ${sinProgramar.codigo ?? ''}. Si la unidad se desvía para cargarla, suma aquí su ruta de la tabla o los galones: sube el tope del tramo. Si no gasta más, cierra esta ventana.`}
                    textoCerrar="No hace falta"
                    onCerrar={() => setSinProgramar(null)}
                />
            )}

            {marcando && (
                <SinTicketDialog
                    key={marcando.id}
                    guia={marcando}
                    onCerrar={() => setMarcando(null)}
                />
            )}
        </div>
    );
}
