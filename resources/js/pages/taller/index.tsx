import { Head, setLayoutProps, usePoll } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import taller from '@/actions/App/Http/Controllers/TallerController';
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

const ETAPA: Record<EtapaVuelta, string> = {
    programada: 'Por salir',
    en_ida: 'En ida',
    en_vuelta: 'En vuelta',
    en_base: 'En base',
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
 * La bandeja de taller: cada ticket abierto espera el apto antes de pasar a
 * abastecimiento. Si no es apto, taller dice cuándo estará lista la unidad.
 */
export default function Taller({ tickets }: { tickets: Ticket[] }) {
    const [buscar, setBuscar] = useState('');
    const [revisando, setRevisando] = useState<Ticket | null>(null);

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
                        <div className="overflow-x-auto rounded-xl border bg-card">
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
                                            <TableCell className="font-mono whitespace-nowrap">
                                                {formatearPlaca(ticket.placa)}
                                                {ticket.carreta && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {formatearPlaca(
                                                            ticket.carreta,
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {ticket.codigo}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {formatearFecha(ticket.inicio)}
                                                <span className="block text-xs text-muted-foreground">
                                                    {ETAPA[ticket.etapa]}
                                                </span>
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
                                                            setRevisando(ticket)
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
                ticket={revisando}
                onCerrar={() => setRevisando(null)}
            />
        </div>
    );
}
