import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import conciliacion from '@/actions/App/Http/Controllers/ConciliacionCombustibleController';
import { galones, soles } from '@/components/abastecimiento/barra-combustible';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
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

type Props = {
    mes: string;
    totales: {
        autorizado: number;
        despachado: number;
        importe: number;
        sin_voucher: number;
        anulados: number;
    };
    porGrifo: {
        punto: string;
        autorizaciones: number;
        autorizado: number;
        despachado: number;
        diferencia: number;
        importe: number;
        sin_voucher: number;
        precio_promedio: number | null;
    }[];
    sinVoucher: {
        id: number;
        fecha: string;
        placa: string;
        ticket: string | null;
        vuelta_id: number | null;
        punto: string | null;
        galones: number;
        atrasado: boolean;
    }[];
    rendimiento: {
        placa: string;
        tickets: number;
        km: number;
        galones: number;
        rendimiento: number | null;
    }[];
};

function Dato({
    titulo,
    valor,
    alerta = false,
}: {
    titulo: string;
    valor: string;
    alerta?: boolean;
}) {
    return (
        <div className="flex flex-col gap-1 rounded-xl border bg-card p-4">
            <span className="text-xs text-muted-foreground">{titulo}</span>
            <span
                className={cn(
                    'text-xl font-semibold tabular-nums',
                    alerta && 'text-amber-700 dark:text-amber-400',
                )}
            >
                {valor}
            </span>
        </div>
    );
}

/**
 * La conciliación del combustible del mes: por grifo, lo autorizado contra
 * lo que dicen los vouchers y lo que costó; lo que sigue sin voucher; y el
 * rendimiento de cada unidad. Es lo que se cruza con el estado de cuenta de
 * Repsol.
 */
export default function ConciliacionCombustible({
    mes,
    totales,
    porGrifo,
    sinVoucher,
    rendimiento,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Abastecimiento', href: abastecimiento.index().url },
            { title: 'Conciliación', href: conciliacion.index().url },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Conciliación de combustible" />

            <EncabezadoPantalla
                titulo="Conciliación de combustible"
                descripcion="Lo autorizado contra los vouchers, por grifo: para cruzar con el estado de cuenta de Repsol."
                icono={Scale}
                acciones={
                    <Input
                        type="month"
                        aria-label="Mes"
                        className="w-44"
                        value={mes}
                        onChange={(evento) =>
                            evento.target.value &&
                            router.get(
                                conciliacion.index({
                                    query: { mes: evento.target.value },
                                }).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    />
                }
            />

            <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                <Dato titulo="Autorizado" valor={galones(totales.autorizado)} />
                <Dato
                    titulo="Despachado (vouchers)"
                    valor={galones(totales.despachado)}
                />
                <Dato titulo="Importe" valor={soles(totales.importe)} />
                <Dato
                    titulo="Sin voucher"
                    valor={String(totales.sin_voucher)}
                    alerta={totales.sin_voucher > 0}
                />
                <Dato titulo="Anulados" valor={String(totales.anulados)} />
            </div>

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Por grifo</h2>
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Punto</TableHead>
                                <TableHead className="text-right">
                                    Autorizaciones
                                </TableHead>
                                <TableHead className="text-right">
                                    Autorizado
                                </TableHead>
                                <TableHead className="text-right">
                                    Despachado
                                </TableHead>
                                <TableHead
                                    className="text-right"
                                    title="Despachado menos autorizado, solo de los que tienen voucher"
                                >
                                    Diferencia
                                </TableHead>
                                <TableHead className="text-right">
                                    S/ gal prom.
                                </TableHead>
                                <TableHead className="text-right">
                                    Importe
                                </TableHead>
                                <TableHead className="text-right">
                                    Sin voucher
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {porGrifo.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={8}
                                        className="py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No se autorizó combustible este mes.
                                    </TableCell>
                                </TableRow>
                            )}
                            {porGrifo.map((fila) => (
                                <TableRow key={fila.punto}>
                                    <TableCell className="font-medium">
                                        {fila.punto}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {fila.autorizaciones}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {galones(fila.autorizado)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {galones(fila.despachado)}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-right tabular-nums',
                                            fila.diferencia > 0 &&
                                                'text-amber-700 dark:text-amber-400',
                                        )}
                                    >
                                        {fila.diferencia > 0 && '+'}
                                        {galones(fila.diferencia)}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {fila.precio_promedio?.toFixed(3) ??
                                            '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {soles(fila.importe)}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-right tabular-nums',
                                            fila.sin_voucher > 0 &&
                                                'text-amber-700 dark:text-amber-400',
                                        )}
                                    >
                                        {fila.sin_voucher}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">
                        Autorizado sin voucher
                    </h2>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Unidad</TableHead>
                                    <TableHead>Ticket</TableHead>
                                    <TableHead>Punto</TableHead>
                                    <TableHead className="text-right">
                                        Galones
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sinVoucher.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-sm text-muted-foreground"
                                        >
                                            Todo lo autorizado tiene su voucher.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {sinVoucher.map((fila) => (
                                    <TableRow key={fila.id}>
                                        <TableCell
                                            className={cn(
                                                'whitespace-nowrap tabular-nums',
                                                fila.atrasado &&
                                                    'font-medium text-amber-700 dark:text-amber-400',
                                            )}
                                        >
                                            {formatearFecha(fila.fecha)}
                                        </TableCell>
                                        <TableCell className="font-mono">
                                            {formatearPlaca(fila.placa)}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {fila.vuelta_id ? (
                                                <Link
                                                    href={
                                                        abastecimiento.index({
                                                            query: {
                                                                ticket: fila.vuelta_id,
                                                            },
                                                        }).url
                                                    }
                                                    className="underline-offset-2 hover:underline"
                                                >
                                                    {fila.ticket}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {fila.punto ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {galones(fila.galones)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">
                        Rendimiento por unidad
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Tickets que llegaron a la base este mes, con el km de
                        salida (OK de taller) y el de llegada. De menor a mayor
                        rendimiento: los de arriba son los que hay que revisar.
                    </p>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Unidad</TableHead>
                                    <TableHead className="text-right">
                                        Tickets
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Km
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Galones
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Km/gal
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rendimiento.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-sm text-muted-foreground"
                                        >
                                            Todavía no hay tickets con los dos
                                            kilometrajes.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {rendimiento.map((fila) => (
                                    <TableRow key={fila.placa}>
                                        <TableCell className="font-mono">
                                            {formatearPlaca(fila.placa)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {fila.tickets}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {fila.km.toLocaleString('es-PE')}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {galones(fila.galones)}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {fila.rendimiento?.toFixed(2) ??
                                                '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>
    );
}
