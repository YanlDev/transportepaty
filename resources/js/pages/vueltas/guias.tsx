import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ExternalLink,
    FileStack,
    Search,
    Undo2,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input';
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
import { cn } from '@/lib/utils';

type Sentido = 'ida' | 'regreso';

type DatosGuia = {
    id: number;
    numero_gr: string;
    fecha: string;
    placa: string;
    carreta: string | null;
    tiene_unidad: boolean;
    conductor_id: number | null;
    conductor: string | null;
    origen: string | null;
    destino: string | null;
    carga: string;
    peso: string;
    cliente: string;
    destinatario: string | null;
};

type TicketAbierto = {
    id: number;
    codigo: string | null;
    inicio: string | null;
    estado_label: string;
    conductor_id: number | null;
    conductor: string | null;
    carreta: string | null;
    /** Las GR que ya tiene asignadas. */
    guias: number;
    /** Todo el recorrido, vacíos incluidos. */
    recorrido: {
        sentido: Sentido;
        texto: string;
        carga: string;
        guias: number;
    }[];
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

/** Días desde el traslado: una GR vieja en la bandeja es la que urge. */
const diasDesde = (fecha: string) =>
    Math.max(
        0,
        Math.floor(
            (Date.now() - new Date(`${fecha}T00:00:00`).getTime()) / 86_400_000,
        ),
    );

const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

type Filtro = 'todas' | 'con-ticket' | 'sin-ticket' | 'fuera-padron';

const estadoDe = (guia: Pendiente): Exclude<Filtro, 'todas'> =>
    !guia.tiene_unidad
        ? 'fuera-padron'
        : guia.tickets.length === 0
          ? 'sin-ticket'
          : 'con-ticket';

const FILTROS: { valor: Filtro; texto: string }[] = [
    { valor: 'todas', texto: 'Todas' },
    { valor: 'con-ticket', texto: 'Con ticket abierto' },
    { valor: 'sin-ticket', texto: 'Sin ticket abierto' },
    { valor: 'fuera-padron', texto: 'Placa fuera del padrón' },
];

/**
 * El ticket elegido, para reconocerlo antes de asignar: cuándo salió, quién
 * maneja, con qué carreta y su recorrido. Avisa si no calza con la GR.
 */
function FichaTicket({
    ticket,
    guia,
}: {
    ticket: TicketAbierto;
    guia: Pendiente;
}) {
    const otroConductor =
        guia.conductor_id !== null &&
        ticket.conductor_id !== null &&
        guia.conductor_id !== ticket.conductor_id;
    const otraCarreta =
        guia.carreta !== null &&
        ticket.carreta !== null &&
        guia.carreta.replace('-', '') !== ticket.carreta.replace('-', '');

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-muted/50 p-3 text-sm">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span className="font-medium">
                    {ticket.codigo} · {ticket.estado_label}
                </span>
                <span className="text-muted-foreground">
                    {ticket.inicio
                        ? `Sale ${formatearFecha(ticket.inicio)}`
                        : 'Sin fecha de salida'}
                </span>
                <span
                    className={cn(
                        'flex items-center gap-1',
                        otroConductor && 'text-amber-700 dark:text-amber-400',
                    )}
                >
                    <UserRound className="size-3.5 shrink-0" />
                    {ticket.conductor ?? 'Sin conductor'}
                </span>
                {ticket.carreta && (
                    <span
                        className={cn(
                            'flex items-center gap-1.5',
                            otraCarreta && 'text-amber-700 dark:text-amber-400',
                        )}
                    >
                        Carreta <Placa placa={ticket.carreta} tamano="sm" />
                    </span>
                )}
                <span className="text-muted-foreground">
                    {ticket.guias === 0
                        ? 'Ninguna GR todavía'
                        : `${ticket.guias} GR asignada${ticket.guias === 1 ? '' : 's'}`}
                </span>
                <Link
                    href={vueltasRutas.show(ticket.id).url}
                    className="ml-auto flex items-center gap-1 text-xs underline underline-offset-2"
                >
                    Ver ticket
                    <ExternalLink className="size-3" />
                </Link>
            </div>
            <div className="grid gap-1 sm:grid-cols-2">
                {(['ida', 'regreso'] as const).map((sentido) => {
                    const tramos = ticket.recorrido.filter(
                        (parada) => parada.sentido === sentido,
                    );

                    return (
                        <div key={sentido} className="flex flex-col">
                            <span className="text-xs font-medium text-muted-foreground">
                                {nombreTramo(sentido)}
                            </span>
                            {tramos.length === 0 ? (
                                <span className="text-xs text-muted-foreground">
                                    Sin programar
                                </span>
                            ) : (
                                tramos.map((parada, indice) => (
                                    <span key={indice} className="text-xs">
                                        {parada.texto}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {parada.carga}
                                            {parada.guias > 0 &&
                                                ` · ${parada.guias} GR`}
                                        </span>
                                    </span>
                                ))
                            )}
                        </div>
                    );
                })}
            </div>
            {(otroConductor || otraCarreta) && (
                <p className="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                    <AlertTriangle className="size-3.5 shrink-0" />
                    La GR trae{' '}
                    {[
                        otroConductor && `otro conductor (${guia.conductor})`,
                        otraCarreta && `otra carreta (${guia.carreta})`,
                    ]
                        .filter(Boolean)
                        .join(' y ')}
                    : revisa que sea este ticket.
                </p>
            )}
        </div>
    );
}

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
    const dias = diasDesde(guia.fecha);

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
                        {guia.carreta && (
                            <Placa placa={guia.carreta} tamano="sm" />
                        )}
                        <span className="font-mono text-sm">
                            {guia.numero_gr}
                        </span>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {formatearFecha(guia.fecha)}
                        </span>
                        {dias > 0 && (
                            <span
                                className={cn(
                                    'rounded-full px-1.5 text-xs tabular-nums',
                                    dias >= 3
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                hace {dias} día{dias === 1 ? '' : 's'}
                            </span>
                        )}
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
                        {guia.destinatario &&
                            guia.destinatario !== guia.cliente &&
                            ` → ${guia.destinatario}`}
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
                <>
                    {puedeAsignar && (
                        <div className="grid gap-2 md:grid-cols-[18rem_1fr_auto] md:items-end">
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
                                                    {opcion.inicio &&
                                                        ` · ${formatearFecha(opcion.inicio)}`}
                                                    {opcion.id ===
                                                        guia.sugerencia
                                                            .vuelta_id &&
                                                        ' (sugerido)'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <Field
                                label="Parada"
                                error={
                                    errores.parada_vuelta_id ?? errores.sentido
                                }
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
                                                            {nombreTramo(
                                                                sentido,
                                                            )}
                                                        </SelectLabel>
                                                        {ticket?.paradas
                                                            .filter(
                                                                (parada) =>
                                                                    parada.sentido ===
                                                                    sentido,
                                                            )
                                                            .map((parada) => (
                                                                <SelectItem
                                                                    key={
                                                                        parada.id
                                                                    }
                                                                    value={String(
                                                                        parada.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        parada.texto
                                                                    }{' '}
                                                                    ·{' '}
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
                                                            value={nueva(
                                                                sentido,
                                                            )}
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
                    )}
                    {ticket && <FichaTicket ticket={ticket} guia={guia} />}
                </>
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
    const [buscar, setBuscar] = useState('');
    const [filtro, setFiltro] = useState<Filtro>('todas');

    const encontradas = useMemo(() => {
        const palabras = normalizar(buscar).split(/\s+/).filter(Boolean);

        return pendientes.filter((guia) => {
            const texto = normalizar(
                [
                    guia.placa,
                    guia.placa.replace('-', ''),
                    guia.carreta ?? '',
                    guia.numero_gr,
                    guia.conductor ?? '',
                    guia.cliente,
                    guia.destinatario ?? '',
                    guia.origen ?? '',
                    guia.destino ?? '',
                    ...guia.tickets.map((ticket) => ticket.codigo ?? ''),
                ].join(' '),
            );

            return palabras.every((palabra) => texto.includes(palabra));
        });
    }, [pendientes, buscar]);
    const visibles = encontradas.filter(
        (guia) => filtro === 'todas' || estadoDe(guia) === filtro,
    );
    const cuantas = (valor: Filtro) =>
        valor === 'todas'
            ? encontradas.length
            : encontradas.filter((guia) => estadoDe(guia) === valor).length;
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
                acciones={
                    pendientes.length > 0 && (
                        <div className="relative flex-1 sm:w-72 sm:flex-none">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={buscar}
                                onChange={(evento) =>
                                    setBuscar(evento.target.value)
                                }
                                placeholder="Placa, GR, conductor, cliente o ticket"
                                className="pl-8"
                            />
                        </div>
                    )
                }
            />

            {pendientes.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {FILTROS.map(({ valor, texto }) => {
                        const total = cuantas(valor);

                        return (
                            <Button
                                key={valor}
                                size="sm"
                                variant={
                                    filtro === valor ? 'default' : 'outline'
                                }
                                onClick={() => setFiltro(valor)}
                                disabled={valor !== 'todas' && total === 0}
                            >
                                {texto}
                                <span className="tabular-nums opacity-70">
                                    {total}
                                </span>
                            </Button>
                        );
                    })}
                </div>
            )}

            {pendientes.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No hay GR por asignar.
                </p>
            ) : visibles.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Ninguna GR coincide con la búsqueda.
                </p>
            ) : (
                <div className="flex flex-col gap-3">
                    {visibles.map((guia) => (
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
