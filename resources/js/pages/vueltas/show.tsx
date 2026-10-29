import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    CircleAlert,
    Fuel,
    Lock,
    LockOpen,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import abastecimientoRutas from '@/actions/App/Http/Controllers/AbastecimientoController';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import {
    BarraCombustible,
    galones,
    soles,
} from '@/components/abastecimiento/barra-combustible';
import { Placa } from '@/components/placa';
import { TallerVuelta } from '@/components/taller/taller-vuelta';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { AvisosVuelta } from '@/components/vueltas/avisos-vuelta';
import { EtapaBadge } from '@/components/vueltas/etapa-badge';
import { ItinerarioVuelta } from '@/components/vueltas/itinerario-vuelta';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { usePermisos } from '@/hooks/use-permisos';
import { colorDeSentido } from '@/lib/etapas';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TramoCombustible, ViaticoTicket } from '@/types/abastecimiento';
import type {
    AvisoVuelta,
    GuiaTicket,
    OpcionesVuelta,
    PasoTicket,
    TarjetaVuelta,
} from '@/types/vueltas';

type Gastos = {
    tramos: TramoCombustible[];
    total_registrado: number;
    importe_registrado: number;
    viaticos: ViaticoTicket[];
    viatico_depositado: number;
    viatico_pendiente: number;
    abastecimientos: {
        id: number;
        fecha: string;
        punto: string | null;
        galones: number;
        tanque_lleno: boolean;
        precio_galon: number | null;
        importe: number | null;
        registrado_por: string | null;
    }[];
};

type Props = OpcionesVuelta & {
    ticket: TarjetaVuelta;
    pasos: PasoTicket[];
    /** El paso en el que va: el primero sin terminar. */
    actual: PasoTicket['clave'] | null;
    gastos: Gastos;
    guias: GuiaTicket[];
    aviso: AvisoVuelta;
    puedeAvisar: boolean;
    whatsappConectado: boolean;
};

type Pestana = 'recorrido' | 'taller' | 'gastos' | 'guias' | 'costo' | 'avisos';

/** El color de cada estado de un paso, igual en todo el sistema. */
const ESTILO_PASO: Record<PasoTicket['estado'], string> = {
    hecho: 'bg-emerald-500 text-white border-emerald-500',
    parcial:
        'bg-amber-100 text-amber-800 border-amber-500 dark:bg-amber-950 dark:text-amber-200',
    alerta: 'bg-red-500 text-white border-red-500',
    pendiente: 'bg-background text-muted-foreground border-border',
};

/** `Y-m-d` o `Y-m-d H:i` a `dd/mm` o `dd/mm hh:mm`. */
function fechaCorta(fecha: string): string {
    const [dia, hora] = fecha.split(' ');
    const [, mes, d] = dia.split('-');

    return hora ? `${d}/${mes} ${hora}` : `${d}/${mes}`;
}

/**
 * La línea de pasos del ticket: lo hecho en verde, lo a medias en ámbar, lo
 * que frena (no apto) en rojo y lo que falta en gris. El paso en que va se
 * marca con un anillo.
 */
function LineaDePasos({
    pasos,
    actual,
}: {
    pasos: PasoTicket[];
    actual: PasoTicket['clave'] | null;
}) {
    return (
        <ol className="flex gap-0 overflow-x-auto rounded-xl border bg-card p-4">
            {pasos.map((paso, indice) => {
                const esActual = paso.clave === actual;

                return (
                    <li
                        key={paso.clave}
                        className="flex min-w-36 flex-1 flex-col gap-2"
                    >
                        <div className="flex items-center">
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                                    ESTILO_PASO[paso.estado],
                                    esActual &&
                                        'ring-4 ring-primary/30 ring-offset-0',
                                )}
                            >
                                {paso.estado === 'hecho' ? (
                                    <Check className="size-4" />
                                ) : paso.estado === 'alerta' ? (
                                    <CircleAlert className="size-4" />
                                ) : (
                                    indice + 1
                                )}
                            </span>
                            {indice < pasos.length - 1 && (
                                <span
                                    className={cn(
                                        'h-0.5 flex-1',
                                        paso.estado === 'hecho'
                                            ? 'bg-emerald-500'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </div>
                        <div className="pr-3">
                            <p
                                className={cn(
                                    'text-xs font-semibold',
                                    esActual && 'text-primary',
                                )}
                            >
                                {paso.titulo}
                                {esActual && (
                                    <span className="ml-1 font-normal">
                                        · ahora
                                    </span>
                                )}
                            </p>
                            {paso.detalle && (
                                <p className="text-[11px] leading-snug text-muted-foreground">
                                    {paso.detalle}
                                </p>
                            )}
                            {(paso.fecha || paso.por) && (
                                <p className="text-[11px] text-muted-foreground/80">
                                    {[
                                        paso.fecha && fechaCorta(paso.fecha),
                                        paso.por,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

function Tarjeta({
    titulo,
    valor,
    detalle,
}: {
    titulo: string;
    valor: string;
    detalle?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <p className="text-xs text-muted-foreground">{titulo}</p>
            <p className="text-xl font-semibold tabular-nums">{valor}</p>
            {detalle && (
                <p className="text-xs text-muted-foreground">{detalle}</p>
            )}
        </div>
    );
}

/**
 * El expediente del ticket: arriba, en qué paso va y quién hizo cada uno;
 * abajo, por pestañas, el recorrido, el taller, el combustible y los
 * viáticos, las GR, lo que costó y los avisos. Cada área sigue trabajando
 * en su bandeja: desde acá se salta a ella con el ticket ya abierto.
 */
export default function ExpedienteTicket({
    ticket,
    pasos,
    actual,
    gastos,
    guias,
    aviso,
    puedeAvisar,
    whatsappConectado,
    ...opciones
}: Props) {
    const { puede } = usePermisos();
    const [pestana, setPestana] = useState<Pestana>('recorrido');
    const [editando, setEditando] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Programación', href: vueltasRutas.index().url },
            {
                title: ticket.codigo ?? 'Ticket',
                href: vueltasRutas.show(ticket.id).url,
            },
        ],
    });

    const sinPrecio = gastos.abastecimientos.some(
        (registro) => registro.importe === null,
    );
    const gastoTotal = gastos.importe_registrado + gastos.viatico_depositado;
    const enAbastecimiento =
        puede('abastecimiento.ver') || puede('abastecimiento.viaticos');

    const pestanas: { valor: Pestana; titulo: string }[] = [
        { valor: 'recorrido', titulo: 'Recorrido' },
        { valor: 'taller', titulo: 'Taller' },
        { valor: 'gastos', titulo: 'Combustible y viáticos' },
        { valor: 'guias', titulo: `GR (${guias.length})` },
        { valor: 'costo', titulo: 'Costo' },
        { valor: 'avisos', titulo: 'Avisos' },
    ];

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6">
            <Head
                title={`${ticket.codigo} · ${formatearPlaca(ticket.placa)}`}
            />

            {/* Cabecera: la unidad, el ticket y lo que se puede hacer */}
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Placa placa={ticket.placa} tamano="lg" />
                        <span className="font-mono text-sm text-muted-foreground">
                            {ticket.codigo}
                        </span>
                        {ticket.estado === 'en_curso' ? (
                            <EtapaBadge etapa={ticket.etapa} />
                        ) : (
                            <EstadoVueltaBadge vuelta={ticket} />
                        )}
                        {ticket.estado === 'cerrada' && (
                            <Lock className="size-4 text-muted-foreground" />
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {ticket.tipo_label}
                        {ticket.inicio &&
                            ` · sale ${formatearFecha(ticket.inicio)}`}
                        {' · '}
                        {ticket.conductor ?? 'Sin conductor'}
                        {ticket.carreta &&
                            ` · carreta ${formatearPlaca(ticket.carreta)}`}
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {ticket.editable && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditando(true)}
                        >
                            <Pencil className="size-3.5" />
                            Editar
                        </Button>
                    )}
                    {ticket.cerrable && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `¿Cerrar el ticket ${ticket.codigo}? Ya no se le podrá registrar combustible ni viáticos.`,
                                    )
                                ) {
                                    router.post(
                                        vueltasRutas.cerrar(ticket.id).url,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                        >
                            <Lock className="size-3.5" />
                            Cerrar ticket
                        </Button>
                    )}
                    {ticket.reabrible && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    vueltasRutas.reabrir(ticket.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <LockOpen className="size-3.5" />
                            Reabrir
                        </Button>
                    )}
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={vueltasRutas.unidad(ticket.tracto_id).url}>
                            Historia de la unidad
                            <ChevronRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </div>

            <LineaDePasos pasos={pasos} actual={actual} />

            {/* Pestañas */}
            <div className="flex flex-col gap-4">
                <nav
                    aria-label="Secciones del ticket"
                    className="flex gap-1 overflow-x-auto border-b"
                >
                    {pestanas.map((opcion) => (
                        <button
                            key={opcion.valor}
                            type="button"
                            onClick={() => setPestana(opcion.valor)}
                            aria-current={
                                pestana === opcion.valor ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition',
                                pestana === opcion.valor
                                    ? 'border-primary font-medium text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {opcion.titulo}
                        </button>
                    ))}
                </nav>

                {pestana === 'recorrido' && (
                    <div className="flex flex-col gap-4">
                        <div className="rounded-xl bg-muted/40 p-4">
                            <ItinerarioVuelta
                                paradas={[...ticket.ida, ...ticket.regreso].map(
                                    (parada) => ({
                                        sentido: parada.sentido,
                                        desde: parada.origen ?? '¿?',
                                        hasta: parada.destino ?? '¿?',
                                        carga:
                                            parada.tipo_carga === 'vacio'
                                                ? 'vacía'
                                                : parada.tipo_carga_label,
                                        vacio: parada.tipo_carga === 'vacio',
                                        hecha: parada.guias.length > 0,
                                    }),
                                )}
                            />
                            {ticket.regreso.length === 0 && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Vuelta por definir.
                                </p>
                            )}
                        </div>
                        <TramosVuelta vuelta={ticket} conGuias />
                    </div>
                )}

                {pestana === 'taller' && <TallerVuelta ticket={ticket} />}

                {pestana === 'gastos' && (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                Lo que dice la pizarra contra lo que se
                                registró.
                            </p>
                            {enAbastecimiento && (
                                <Button size="sm" asChild>
                                    <Link
                                        href={
                                            abastecimientoRutas.index({
                                                query: { ticket: ticket.id },
                                            }).url
                                        }
                                    >
                                        <Fuel className="size-3.5" />
                                        Registrar en Abastecimiento
                                    </Link>
                                </Button>
                            )}
                        </div>

                        <section className="flex flex-col gap-2">
                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Combustible
                            </h3>
                            <BarraCombustible tramos={gastos.tramos} />
                            {gastos.abastecimientos.length > 0 && (
                                <div className="overflow-x-auto rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead>Fecha</TableHead>
                                                <TableHead>Punto</TableHead>
                                                <TableHead className="text-right">
                                                    Galones
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    S/ gal
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Importe
                                                </TableHead>
                                                <TableHead>Registró</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {gastos.abastecimientos.map(
                                                (registro) => (
                                                    <TableRow key={registro.id}>
                                                        <TableCell className="tabular-nums">
                                                            {formatearFecha(
                                                                registro.fecha,
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {registro.punto ??
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {galones(
                                                                registro.galones,
                                                            )}
                                                            {registro.tanque_lleno &&
                                                                ' (full)'}
                                                        </TableCell>
                                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                                            {registro.precio_galon?.toFixed(
                                                                3,
                                                            ) ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {soles(
                                                                registro.importe,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {registro.registrado_por ??
                                                                '—'}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </section>

                        <section className="flex flex-col gap-2">
                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Viáticos
                            </h3>
                            {gastos.viaticos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    La pizarra no le asigna viáticos y no se
                                    depositó ninguno.
                                </p>
                            ) : (
                                <ul className="flex flex-col divide-y rounded-xl border">
                                    {gastos.viaticos.map((viatico, indice) => (
                                        <li
                                            key={`${viatico.linea_id ?? 'imprevisto'}-${indice}`}
                                            className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                                        >
                                            <span>
                                                {viatico.concepto}
                                                {viatico.deposito && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {formatearFecha(
                                                            viatico.deposito
                                                                .fecha,
                                                        )}
                                                        {viatico.deposito
                                                            .numero_operacion &&
                                                            ` · op. ${viatico.deposito.numero_operacion}`}
                                                        {viatico.deposito
                                                            .registrado_por &&
                                                            ` · ${viatico.deposito.registrado_por}`}
                                                    </span>
                                                )}
                                            </span>
                                            {viatico.deposito ? (
                                                <span className="text-right tabular-nums">
                                                    {soles(
                                                        viatico.deposito.monto,
                                                    )}
                                                    {viatico.previsto !==
                                                        null &&
                                                        viatico.previsto !==
                                                            viatico.deposito
                                                                .monto && (
                                                            <span className="block text-xs text-muted-foreground">
                                                                pizarra{' '}
                                                                {soles(
                                                                    viatico.previsto,
                                                                )}
                                                            </span>
                                                        )}
                                                </span>
                                            ) : (
                                                <StatusBadge
                                                    label={`Pendiente · ${soles(viatico.previsto)}`}
                                                    tone="warning"
                                                />
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}

                {pestana === 'guias' &&
                    (guias.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Todavía no tiene GR. Cuando se suba la de ida, cae
                            sola en su parada.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>N° GR</TableHead>
                                        <TableHead>Tramo</TableHead>
                                        <TableHead>Carga</TableHead>
                                        <TableHead>Cliente</TableHead>
                                        <TableHead className="text-right">
                                            Peso
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {guias.map((guia) => (
                                        <TableRow key={guia.id}>
                                            <TableCell className="tabular-nums">
                                                {formatearFecha(
                                                    guia.fecha_traslado,
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {guia.numero_gr}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={cn(
                                                        'mr-1.5 text-xs font-medium',
                                                        colorDeSentido(
                                                            guia.sentido,
                                                        ),
                                                    )}
                                                >
                                                    {guia.sentido === 'ida'
                                                        ? 'Ida'
                                                        : 'Vuelta'}
                                                </span>
                                                {guia.tramo}
                                            </TableCell>
                                            <TableCell>
                                                {guia.tipo_carga_label}
                                            </TableCell>
                                            <TableCell className="max-w-56 truncate">
                                                {guia.cliente}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {guia.peso}{' '}
                                                {guia.unidad_peso === 'KGM'
                                                    ? 'kg'
                                                    : 't'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ))}

                {pestana === 'costo' && (
                    <div className="flex flex-col gap-3">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Tarjeta
                                titulo="Combustible"
                                valor={soles(gastos.importe_registrado)}
                                detalle={`${galones(gastos.total_registrado)} autorizados`}
                            />
                            <Tarjeta
                                titulo="Viáticos depositados"
                                valor={soles(gastos.viatico_depositado)}
                                detalle={
                                    gastos.viatico_pendiente > 0
                                        ? `Faltan ${soles(gastos.viatico_pendiente)} según la pizarra`
                                        : undefined
                                }
                            />
                            <Tarjeta
                                titulo="Gasto del ticket"
                                valor={soles(gastoTotal)}
                                detalle="Combustible + viáticos"
                            />
                        </div>
                        {sinPrecio && (
                            <p className="text-xs text-amber-700 dark:text-amber-400">
                                Hay combustible registrado sin precio: no suma
                                en soles.
                            </p>
                        )}
                    </div>
                )}

                {pestana === 'avisos' && (
                    <AvisosVuelta
                        salida={{ id: ticket.id, aviso }}
                        conectado={whatsappConectado}
                        puedeAvisar={puedeAvisar}
                    />
                )}
            </div>

            {editando && (
                <ProgramarVueltaDialog
                    key={ticket.id}
                    open
                    onOpenChange={(abierto) => !abierto && setEditando(false)}
                    vuelta={ticket}
                    opciones={opciones}
                />
            )}
        </div>
    );
}
