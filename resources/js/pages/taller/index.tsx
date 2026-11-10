import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import {
    ArrowUpFromLine,
    ChevronRight,
    CircleCheck,
    CircleDot,
    CircleX,
    ClipboardCheck,
    ClipboardPlus,
    Gauge,
    Search,
    Wrench,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import taller from '@/actions/App/Http/Controllers/TallerController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { RegistrarKmDialog } from '@/components/mantenimiento/registrar-km-dialog';
import { Placa } from '@/components/placa';
import { AvisosUnidad } from '@/components/taller/avisos-unidad';
import type { AvisoTaller } from '@/components/taller/avisos-unidad';
import {
    EstadoTallerBadge,
    fechaHoraTaller,
} from '@/components/taller/estado-taller';
import { RevisionTallerDialog } from '@/components/taller/revision-taller-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { EtapaBadge } from '@/components/vueltas/etapa-badge';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create as nuevoVale } from '@/routes/almacen/salidas';
import {
    show as fichaDeUnidad,
    index as mantenimientoInicio,
    preventivo,
} from '@/routes/mantenimiento';
import { index as llantas } from '@/routes/mantenimiento/llantas';
import {
    edit as abrirOrden,
    create as nuevaOrden,
} from '@/routes/mantenimiento/ordenes';
import type { TractoKm } from '@/types/mantenimiento';
import type { EtapaVuelta, RevisionTaller } from '@/types/vueltas';

type Ticket = {
    id: number;
    codigo: string | null;
    vehiculo_id: number;
    placa: string;
    carreta: string | null;
    /** El último km conocido de la unidad: se propone como km de salida. */
    km_actual: number | null;
    /** Orden abierta, preventivo y llantas, del tracto y de la carreta. */
    avisos: AvisoTaller[];
    conductor: string | null;
    inicio: string | null;
    etapa: EtapaVuelta;
    ruta: string | null;
    revision: RevisionTaller | null;
    anteriores: RevisionTaller[];
    revisable: boolean;
};

/** Una orden de trabajo sin terminar: la unidad está en taller. */
type OrdenAbierta = {
    id: number;
    numero: string;
    vehiculo_id: number;
    placa: string;
    tipo: string;
    servicio: string | null;
    estado: string;
    fecha: string;
    trabajo: string | null;
    /** Materiales del preventivo que todavía no salieron del almacén. */
    faltan: number;
};

/** Una unidad que llegó a base y todavía no tiene el km anotado. */
type Llegada = TractoKm & { codigo: string | null; llegada: string | null };

type Props = {
    tickets: Ticket[];
    ordenes: OrdenAbierta[];
    llegadas: Llegada[];
    puede: { ver_unidad: boolean; registrar_km: boolean };
};

const sinGuion = (texto: string) => texto.toLowerCase().replace(/-/g, '');

/**
 * Las tres bandejas en el orden en que se trabajan: lo que nadie revisó, lo
 * que está en taller y lo que ya salió apto.
 */
const GRUPOS = [
    {
        clave: 'por_revisar',
        titulo: 'Por revisar',
        vacio: 'No hay tickets esperando revisión.',
        filtro: (ticket: Ticket) => ticket.revision === null,
    },
    {
        clave: 'no_apto',
        titulo: 'No aptos (en taller)',
        vacio: 'Ninguna unidad está en taller.',
        filtro: (ticket: Ticket) => ticket.revision?.resultado === 'no_apto',
    },
    {
        clave: 'apto',
        titulo: 'Aptos',
        vacio: 'Todavía no hay aptos.',
        filtro: (ticket: Ticket) => ticket.revision?.resultado === 'apto',
    },
] as const;

/**
 * Un ticket en el celular: la placa grande, a dónde va y los dos botones de
 * siempre. Pensado para usarse en el patio, con una mano.
 */
function TarjetaTaller({
    ticket,
    grupo,
    verUnidad,
    onRevisar,
}: {
    ticket: Ticket;
    grupo: (typeof GRUPOS)[number]['clave'];
    verUnidad: boolean;
    onRevisar: (resultado?: 'apto' | 'no_apto') => void;
}) {
    return (
        <article className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-col gap-1">
                    <Placa placa={ticket.placa} tamano="lg" />
                    {ticket.carreta && (
                        <span className="font-mono text-xs text-muted-foreground">
                            Carreta {formatearPlaca(ticket.carreta)}
                        </span>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1">
                    <Link
                        href={vueltas.show(ticket.id).url}
                        className="font-mono text-xs text-muted-foreground underline-offset-2 hover:underline"
                    >
                        {ticket.codigo}
                    </Link>
                    <EtapaBadge etapa={ticket.etapa} />
                </div>
            </div>

            <div className="text-sm">
                <p className="font-medium">{ticket.ruta ?? 'Sin ruta'}</p>
                <p className="text-muted-foreground">
                    Sale {formatearFecha(ticket.inicio)} ·{' '}
                    {ticket.conductor ?? 'Sin conductor'}
                </p>
            </div>

            <AvisosUnidad avisos={ticket.avisos} />

            {ticket.revision && (
                <div className="flex flex-col gap-1 text-xs">
                    <EstadoTallerBadge revision={ticket.revision} />
                    {ticket.revision.motivo && (
                        <span className="text-muted-foreground">
                            {ticket.revision.motivo}
                        </span>
                    )}
                </div>
            )}

            {ticket.revisable &&
                (grupo === 'apto' ? (
                    <Button
                        variant="outline"
                        className="h-11"
                        onClick={() => onRevisar()}
                    >
                        Revisar de nuevo
                    </Button>
                ) : (
                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            className="h-14 bg-emerald-600 text-base text-white hover:bg-emerald-700"
                            onClick={() => onRevisar('apto')}
                        >
                            <CircleCheck className="size-5" />
                            {grupo === 'no_apto' ? 'Ya está lista' : 'Apto'}
                        </Button>
                        <Button
                            variant="outline"
                            className="h-14 border-red-300 text-base text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950"
                            onClick={() => onRevisar('no_apto')}
                        >
                            <CircleX className="size-5" />
                            {grupo === 'no_apto' ? 'Cambiar fecha' : 'No apto'}
                        </Button>
                    </div>
                ))}

            {verUnidad && (
                <Link
                    href={fichaDeUnidad(ticket.vehiculo_id).url}
                    className="flex min-h-11 items-center justify-between rounded-lg border px-3 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Ver la unidad: órdenes, km y materiales
                    <ChevronRight className="size-4" />
                </Link>
            )}
        </article>
    );
}

/**
 * La bandeja de taller: cada ticket abierto espera el apto antes de pasar a
 * abastecimiento. Si no es apto, taller dice cuándo estará lista la unidad.
 */
export default function Taller({ tickets, ordenes, llegadas, puede }: Props) {
    const [kmDe, setKmDe] = useState<number | null>(null);
    const [buscar, setBuscar] = useState('');
    const [revisando, setRevisando] = useState<{
        ticket: Ticket;
        resultado?: 'apto' | 'no_apto';
    } | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Taller', href: taller.index().url }],
    });

    const { puede: tienePermiso } = usePermisos();
    const atajos = [
        ...(tienePermiso('mantenimiento.gestionar')
            ? [
                  {
                      titulo: 'Nueva orden',
                      href: nuevaOrden().url,
                      icono: ClipboardPlus,
                  },
              ]
            : []),
        ...(tienePermiso('almacen.despachar')
            ? [
                  {
                      titulo: 'Despachar vale',
                      href: nuevoVale().url,
                      icono: ArrowUpFromLine,
                  },
              ]
            : []),
        { titulo: 'Preventivo', href: preventivo().url, icono: Wrench },
        { titulo: 'Llantas', href: llantas().url, icono: CircleDot },
        {
            titulo: 'Registrar km',
            href: mantenimientoInicio().url,
            icono: Gauge,
        },
    ];

    // Programación arma tickets todo el día: aparecen sin recargar.
    usePoll(30_000, { only: ['tickets', 'ordenes', 'llegadas'] });

    const filtrados = useMemo(() => {
        const texto = sinGuion(buscar.trim());

        return texto === ''
            ? tickets
            : tickets.filter(
                  (ticket) =>
                      sinGuion(ticket.placa).includes(texto) ||
                      sinGuion(ticket.codigo ?? '').includes(texto) ||
                      sinGuion(ticket.carreta ?? '').includes(texto),
              );
    }, [buscar, tickets]);

    const bandeja = (grupo: (typeof GRUPOS)[number]) => {
        const lista = filtrados.filter(grupo.filtro);

        return (
            <section key={grupo.clave} className="flex flex-col gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    {grupo.titulo}
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 text-xs tabular-nums',
                            grupo.clave === 'por_revisar' && lista.length > 0
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {lista.length}
                    </span>
                </h2>
                {/* En el celular: una tarjeta por unidad, con los
                            botones grandes para el patio. */}
                <div className="flex flex-col gap-2 md:hidden">
                    {lista.length === 0 && (
                        <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                            {grupo.vacio}
                        </p>
                    )}
                    {lista.map((ticket) => (
                        <TarjetaTaller
                            key={ticket.id}
                            ticket={ticket}
                            grupo={grupo.clave}
                            verUnidad={puede.ver_unidad}
                            onRevisar={(resultado) =>
                                setRevisando({ ticket, resultado })
                            }
                        />
                    ))}
                </div>
                <div className="overflow-x-auto rounded-xl border bg-card max-md:hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Unidad</TableHead>
                                <TableHead>Ticket</TableHead>
                                <TableHead>Sale</TableHead>
                                <TableHead>Ruta</TableHead>
                                <TableHead>Conductor</TableHead>
                                <TableHead>Taller</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lista.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={7}
                                        className="py-5 text-center text-sm text-muted-foreground"
                                    >
                                        {grupo.vacio}
                                    </TableCell>
                                </TableRow>
                            )}
                            {lista.map((ticket) => (
                                <TableRow key={ticket.id}>
                                    <TableCell className="whitespace-nowrap">
                                        <Placa placa={ticket.placa} />
                                        {ticket.carreta && (
                                            <span className="mt-1 block font-mono text-xs text-muted-foreground">
                                                {formatearPlaca(ticket.carreta)}
                                            </span>
                                        )}
                                        <AvisosUnidad
                                            avisos={ticket.avisos}
                                            className="mt-1.5 max-w-64"
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        <Link
                                            href={vueltas.show(ticket.id).url}
                                            className="underline-offset-2 hover:underline"
                                        >
                                            {ticket.codigo}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {formatearFecha(ticket.inicio)}
                                        <EtapaBadge
                                            etapa={ticket.etapa}
                                            className="mt-1"
                                        />
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {ticket.ruta ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {ticket.conductor ?? '—'}
                                    </TableCell>
                                    <TableCell className="max-w-72">
                                        <EstadoTallerBadge
                                            revision={ticket.revision}
                                            corto
                                        />
                                        {ticket.revision?.resultado ===
                                            'no_apto' && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {ticket.revision
                                                    .disponible_estimado &&
                                                    `Lista ${fechaHoraTaller(ticket.revision.disponible_estimado)} · `}
                                                {ticket.revision.motivo}
                                            </span>
                                        )}
                                        {ticket.revision?.resultado ===
                                            'apto' && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {ticket.revision.por} ·{' '}
                                                {fechaHoraTaller(
                                                    ticket.revision.en,
                                                )}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {ticket.revisable && (
                                            <Button
                                                size="sm"
                                                variant={
                                                    grupo.clave === 'apto'
                                                        ? 'ghost'
                                                        : 'default'
                                                }
                                                onClick={() =>
                                                    setRevisando({
                                                        ticket,
                                                    })
                                                }
                                            >
                                                {grupo.clave === 'por_revisar'
                                                    ? 'Revisar'
                                                    : grupo.clave === 'no_apto'
                                                      ? 'Actualizar'
                                                      : 'Revisar de nuevo'}
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </section>
        );
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Taller" />

            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <EncabezadoPantalla
                    titulo="Taller"
                    descripcion="Antes de salir, cada ticket necesita tu apto. Sin él, abastecimiento no puede cargarle nada."
                    icono={ClipboardCheck}
                />
                <div className="relative md:w-64">
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={buscar}
                        onChange={(evento) => setBuscar(evento.target.value)}
                        placeholder="Placa, carreta o ticket"
                        className="pl-8"
                    />
                </div>
            </div>

            {/* Lo demás del taller, a un toque: en el celular la barra de
                abajo solo tiene lugar para dos áreas. */}
            {puede.ver_unidad && (
                <nav
                    aria-label="Atajos de taller"
                    className="-mt-2 flex gap-2 overflow-x-auto pb-1"
                >
                    {atajos.map((atajo) => (
                        <Link
                            key={atajo.titulo}
                            href={atajo.href}
                            className="flex min-h-11 shrink-0 items-center gap-2 rounded-full border bg-card px-4 text-sm font-medium transition-colors hover:bg-accent/50"
                        >
                            <atajo.icono className="size-4 text-muted-foreground" />
                            {atajo.titulo}
                        </Link>
                    ))}
                </nav>
            )}

            {GRUPOS.filter((grupo) => grupo.clave !== 'apto').map(bandeja)}

            {puede.ver_unidad && (
                <section className="flex flex-col gap-2">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        En taller, con orden abierta
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums">
                            {ordenes.length}
                        </span>
                    </h2>
                    {ordenes.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                            No hay órdenes de trabajo abiertas.
                        </p>
                    ) : (
                        <ul className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                            {ordenes.map((orden) => (
                                <li key={orden.id}>
                                    <Link
                                        href={abrirOrden(orden.id).url}
                                        className="flex min-h-16 items-center gap-3 rounded-xl border bg-card p-3 transition-colors hover:bg-accent/50"
                                    >
                                        <Placa placa={orden.placa} />
                                        <span className="flex min-w-0 flex-1 flex-col text-sm">
                                            <span className="font-medium">
                                                {orden.numero} · {orden.tipo}
                                                {orden.servicio &&
                                                    ` ${orden.servicio}`}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {orden.estado} · desde{' '}
                                                {formatearFecha(orden.fecha)}
                                            </span>
                                            {orden.faltan > 0 && (
                                                <span className="text-xs font-medium text-red-700 dark:text-red-300">
                                                    {orden.faltan === 1
                                                        ? 'Falta que salga 1 material del almacén'
                                                        : `Falta que salgan ${orden.faltan} materiales del almacén`}
                                                </span>
                                            )}
                                        </span>
                                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            {puede.registrar_km && llegadas.length > 0 && (
                <section className="flex flex-col gap-2">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        Llegaron a base y falta su km
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 tabular-nums dark:bg-amber-950 dark:text-amber-200">
                            {llegadas.length}
                        </span>
                    </h2>
                    <ul className="flex flex-wrap gap-2">
                        {llegadas.map((llegada) => (
                            <li key={llegada.id}>
                                <button
                                    type="button"
                                    onClick={() => setKmDe(llegada.id)}
                                    className="flex min-h-12 items-center gap-2 rounded-xl border bg-card px-3 text-left text-sm transition-colors hover:bg-accent/50"
                                >
                                    <Gauge className="size-4 text-muted-foreground" />
                                    <Placa placa={llegada.placa} />
                                    <span className="text-xs text-muted-foreground">
                                        llegó {formatearFecha(llegada.llegada)}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                    <RegistrarKmDialog
                        key={kmDe ?? 'cerrado'}
                        tractos={llegadas}
                        vehiculoInicial={kmDe ?? undefined}
                        abierto={kmDe !== null}
                        onAbiertoChange={(abierto) => !abierto && setKmDe(null)}
                    />
                </section>
            )}

            {/* Lo ya resuelto va al final: primero lo que espera. */}
            {GRUPOS.filter((grupo) => grupo.clave === 'apto').map(bandeja)}

            <RevisionTallerDialog
                key={`${revisando?.ticket.id}-${revisando?.resultado ?? ''}`}
                ticket={revisando?.ticket ?? null}
                resultadoInicial={revisando?.resultado}
                onCerrar={() => setRevisando(null)}
            />
        </div>
    );
}
