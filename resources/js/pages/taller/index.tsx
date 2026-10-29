import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { CircleCheck, CircleX, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import taller from '@/actions/App/Http/Controllers/TallerController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Placa } from '@/components/placa';
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
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EtapaVuelta, RevisionTaller } from '@/types/vueltas';

type Ticket = {
    id: number;
    codigo: string | null;
    placa: string;
    carreta: string | null;
    conductor: string | null;
    inicio: string | null;
    etapa: EtapaVuelta;
    ruta: string | null;
    revision: RevisionTaller | null;
    anteriores: RevisionTaller[];
    revisable: boolean;
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
    onRevisar,
}: {
    ticket: Ticket;
    grupo: (typeof GRUPOS)[number]['clave'];
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
        </article>
    );
}

/**
 * La bandeja de taller: cada ticket abierto espera el apto antes de pasar a
 * abastecimiento. Si no es apto, taller dice cuándo estará lista la unidad.
 */
export default function Taller({ tickets }: { tickets: Ticket[] }) {
    const [buscar, setBuscar] = useState('');
    const [revisando, setRevisando] = useState<{
        ticket: Ticket;
        resultado?: 'apto' | 'no_apto';
    } | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Taller', href: taller.index().url }],
    });

    // Programación arma tickets todo el día: aparecen sin recargar.
    usePoll(30_000, { only: ['tickets'] });

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

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Taller" />

            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 className="text-lg font-semibold">
                        Taller · OK de salida
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Sin el apto, el ticket no pasa a abastecimiento.
                    </p>
                </div>
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

            {GRUPOS.map((grupo) => {
                const lista = filtrados.filter(grupo.filtro);

                return (
                    <section key={grupo.clave} className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-sm font-semibold">
                            {grupo.titulo}
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-xs tabular-nums',
                                    grupo.clave === 'por_revisar' &&
                                        lista.length > 0
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
                                                        {formatearPlaca(
                                                            ticket.carreta,
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                <Link
                                                    href={
                                                        vueltas.show(ticket.id)
                                                            .url
                                                    }
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
                                                            grupo.clave ===
                                                            'apto'
                                                                ? 'ghost'
                                                                : 'default'
                                                        }
                                                        onClick={() =>
                                                            setRevisando({
                                                                ticket,
                                                            })
                                                        }
                                                    >
                                                        {grupo.clave ===
                                                        'por_revisar'
                                                            ? 'Revisar'
                                                            : grupo.clave ===
                                                                'no_apto'
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
            })}

            <RevisionTallerDialog
                key={`${revisando?.ticket.id}-${revisando?.resultado ?? ''}`}
                ticket={revisando?.ticket ?? null}
                resultadoInicial={revisando?.resultado}
                onCerrar={() => setRevisando(null)}
            />
        </div>
    );
}
