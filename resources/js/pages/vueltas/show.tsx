import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    CircleAlert,
    Fuel,
    House,
    Lock,
    LockOpen,
    Pencil,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import abastecimientoRutas from '@/actions/App/Http/Controllers/AbastecimientoController';
import guiasRutas from '@/actions/App/Http/Controllers/GuiaPorAsignarController';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import {
    BarraCombustible,
    galones,
    soles,
} from '@/components/abastecimiento/barra-combustible';
import { CombustiblesDelTicket } from '@/components/abastecimiento/combustible-ticket';
import { useConfirmar } from '@/components/confirmar';
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
import { LlegadaDialog } from '@/components/vueltas/llegada-dialog';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { usePermisos } from '@/hooks/use-permisos';
import { colorDeSentido } from '@/lib/etapas';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    CombustibleDelTicket,
    TramoCombustible,
    ViaticoTicket,
} from '@/types/abastecimiento';
import type {
    AvisoVuelta,
    GuiaTicket,
    LiquidacionTicket,
    OpcionesVuelta,
    PasoTicket,
    TarjetaVuelta,
} from '@/types/vueltas';

type Gastos = {
    tramos: TramoCombustible[];
    total_registrado: number;
    total_despachado: number;
    importe_registrado: number;
    viaticos: ViaticoTicket[];
    viatico_depositado: number;
    viatico_pendiente: number;
    abastecimientos: CombustibleDelTicket[];
};

type Props = OpcionesVuelta & {
    ticket: TarjetaVuelta;
    pasos: PasoTicket[];
    /** El paso en el que va: el primero sin terminar. */
    actual: PasoTicket['clave'] | null;
    gastos: Gastos;
    liquidacion: LiquidacionTicket;
    guias: GuiaTicket[];
    aviso: AvisoVuelta;
    puedeAvisar: boolean;
    whatsappConectado: boolean;
};

type Pestana =
    | 'recorrido'
    | 'taller'
    | 'gastos'
    | 'guias'
    | 'liquidacion'
    | 'avisos';

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
    liquidacion,
    guias,
    aviso,
    puedeAvisar,
    whatsappConectado,
    ...opciones
}: Props) {
    const confirmar = useConfirmar();
    const { puede } = usePermisos();
    const [pestana, setPestana] = useState<Pestana>('recorrido');
    const [editando, setEditando] = useState(false);
    const [llegando, setLlegando] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Programación', href: vueltasRutas.index().url },
            {
                title: ticket.codigo ?? 'Ticket',
                href: vueltasRutas.show(ticket.id).url,
            },
        ],
    });

    const enAbastecimiento =
        puede('abastecimiento.ver') || puede('abastecimiento.viaticos');

    const pestanas: { valor: Pestana; titulo: string }[] = [
        { valor: 'recorrido', titulo: 'Recorrido' },
        { valor: 'taller', titulo: 'Taller' },
        { valor: 'gastos', titulo: 'Combustible y viáticos' },
        { valor: 'guias', titulo: `GR (${guias.length})` },
        { valor: 'liquidacion', titulo: 'Liquidación' },
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
                    {ticket.llegada ? (
                        <p className="text-sm text-emerald-700 dark:text-emerald-400">
                            Llegó a la base el{' '}
                            {formatearFecha(ticket.llegada.fecha)}
                            {ticket.llegada.km !== null &&
                                ` con ${ticket.llegada.km.toLocaleString('es-PE')} km`}
                            {ticket.llegada.por && ` · ${ticket.llegada.por}`}
                        </p>
                    ) : (
                        ticket.llegada_prevista && (
                            <p
                                className={cn(
                                    'text-sm',
                                    ticket.atrasada
                                        ? 'font-medium text-red-700 dark:text-red-400'
                                        : 'text-muted-foreground',
                                )}
                            >
                                Debería llegar el{' '}
                                {formatearFecha(ticket.llegada_prevista)}
                                {ticket.atrasada &&
                                    ': ya pasó, registra la llegada o revisa el atraso'}
                            </p>
                        )
                    )}
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
                    {ticket.registrable_llegada && (
                        <Button
                            variant={ticket.llegada ? 'ghost' : 'outline'}
                            size="sm"
                            onClick={() => setLlegando(true)}
                        >
                            <House className="size-3.5" />
                            {ticket.llegada
                                ? 'Corregir llegada'
                                : 'Registrar llegada'}
                        </Button>
                    )}
                    {ticket.cerrable && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPestana('liquidacion')}
                        >
                            <Lock className="size-3.5" />
                            Liquidar y cerrar
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
                                La tarjeta confirmada de cada tramo contra lo
                                autorizado y lo que dicen los vouchers.
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
                                        Gestionar en Abastecimiento
                                    </Link>
                                </Button>
                            )}
                        </div>

                        <section className="flex flex-col gap-2">
                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Combustible
                            </h3>
                            <BarraCombustible tramos={gastos.tramos} />
                            <CombustiblesDelTicket
                                registros={gastos.abastecimientos}
                                puedeGestionar={false}
                                onAccion={() => undefined}
                            />
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
                            Todavía no tiene GR. Se suben en Guías y se asignan
                            al ticket desde{' '}
                            <Link
                                href={guiasRutas.index().url}
                                className="underline underline-offset-2"
                            >
                                GR por asignar
                            </Link>
                            .
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
                                        <TableHead>Asignó</TableHead>
                                        <TableHead className="w-0" />
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
                                                {guia.no_programada && (
                                                    <span className="ml-1.5 rounded border border-dashed border-amber-400 px-1 text-[10px] text-amber-700 dark:text-amber-400">
                                                        no programada
                                                    </span>
                                                )}
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
                                            <TableCell className="text-xs text-muted-foreground">
                                                {guia.asignada_por ?? '—'}
                                                {guia.asignada_at &&
                                                    ` · ${fechaCorta(guia.asignada_at)}`}
                                            </TableCell>
                                            <TableCell>
                                                {guia.quitable && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        title="Sacarla del ticket: vuelve a la bandeja de GR por asignar"
                                                        onClick={async () => {
                                                            if (
                                                                await confirmar(
                                                                    {
                                                                        titulo: `¿Sacar la GR ${guia.numero_gr} del ticket ${ticket.codigo}?`,
                                                                        descripcion:
                                                                            'Vuelve a la bandeja de GR por asignar.',
                                                                        accion: 'Sacar',
                                                                    },
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    guiasRutas.quitar(
                                                                        guia.id,
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Undo2 className="size-3.5" />
                                                        Sacar
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ))}

                {pestana === 'liquidacion' && (
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Tarjeta
                                titulo="Combustible"
                                valor={soles(liquidacion.combustible.estimado)}
                                detalle={
                                    liquidacion.combustible.estimado !==
                                    liquidacion.combustible.importe
                                        ? `${soles(liquidacion.combustible.importe)} con voucher; el resto, estimado por el precio autorizado`
                                        : `${galones(liquidacion.combustible.autorizado)} autorizados · ${galones(liquidacion.combustible.despachado)} despachados`
                                }
                            />
                            <Tarjeta
                                titulo="Viáticos depositados"
                                valor={soles(liquidacion.viaticos.depositado)}
                                detalle={
                                    liquidacion.viaticos.pendiente > 0
                                        ? `Faltan ${soles(liquidacion.viaticos.pendiente)} de la tarjeta`
                                        : undefined
                                }
                            />
                            <Tarjeta
                                titulo="Recorrido"
                                valor={
                                    liquidacion.km.recorridos === null
                                        ? '—'
                                        : `${liquidacion.km.recorridos.toLocaleString('es-PE')} km`
                                }
                                detalle={
                                    liquidacion.rendimiento === null
                                        ? 'Falta el km de salida o de llegada'
                                        : `${liquidacion.rendimiento.toFixed(2)} km por galón`
                                }
                            />
                            <Tarjeta
                                titulo="Gasto del ticket"
                                valor={soles(liquidacion.total)}
                                detalle={
                                    liquidacion.total_es_estimado
                                        ? 'Combustible + viáticos · estimado hasta tener todos los vouchers'
                                        : 'Combustible + viáticos'
                                }
                            />
                        </div>

                        {liquidacion.combustible.diferencia !== 0 && (
                            <p className="text-sm text-muted-foreground">
                                Los vouchers dicen{' '}
                                {liquidacion.combustible.diferencia > 0
                                    ? 'más'
                                    : 'menos'}{' '}
                                que lo autorizado:{' '}
                                {galones(
                                    Math.abs(
                                        liquidacion.combustible.diferencia,
                                    ),
                                )}
                                .
                            </p>
                        )}

                        {liquidacion.pendientes.length > 0 && (
                            <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                <p className="font-medium">
                                    Para cerrar el ticket falta:
                                </p>
                                <ul className="mt-1 list-disc pl-5">
                                    {liquidacion.pendientes.map((pendiente) => (
                                        <li key={pendiente}>{pendiente}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {liquidacion.avisos.length > 0 && (
                            <ul className="list-disc pl-5 text-sm text-muted-foreground">
                                {liquidacion.avisos.map((aviso) => (
                                    <li key={aviso}>{aviso}</li>
                                ))}
                            </ul>
                        )}

                        {ticket.cerrable && (
                            <Button
                                className="self-start"
                                disabled={!liquidacion.puede_cerrar}
                                onClick={async () => {
                                    if (
                                        await confirmar({
                                            titulo: `¿Liquidar y cerrar el ticket ${ticket.codigo}?`,
                                            descripcion:
                                                'Ya no se le podrá registrar nada; para corregir habrá que reabrirlo.',
                                            accion: 'Liquidar y cerrar',
                                            tono: 'normal',
                                        })
                                    ) {
                                        router.post(
                                            vueltasRutas.cerrar(ticket.id).url,
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                <Lock className="size-4" />
                                Liquidar y cerrar
                            </Button>
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

            {llegando && (
                <LlegadaDialog
                    ticket={ticket}
                    onCerrar={() => setLlegando(false)}
                />
            )}

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
