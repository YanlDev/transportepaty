import { Head, Link } from '@inertiajs/react';
import { Info, MapPin, PackageCheck, Truck } from 'lucide-react';
import flota from '@/actions/App/Http/Controllers/FlotaController';
import { MapaFlota } from '@/components/flota/mapa-flota';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { GrupoDescarga, PuntoMapa, ResumenFlota } from '@/types/fleet';

type Props = {
    puntos: PuntoMapa[];
    descargas: GrupoDescarga[];
    resumen: ResumenFlota;
};

export default function FlotaIndex({ puntos, descargas, resumen }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Flota" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">Flota</h1>
                <p className="text-sm text-muted-foreground">
                    {resumen.unidades}{' '}
                    {resumen.unidades === 1 ? 'unidad' : 'unidades'} con
                    posición reportada · {resumen.en_base} en zona base
                    {resumen.sin_posicion > 0 &&
                        ` · ${resumen.sin_posicion} sin ubicar`}
                </p>
            </div>

            {!resumen.estimacion_calibrada && (
                <div className="flex gap-3 rounded-lg border border-amber-500/40 bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
                    <Info className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    <p>
                        Las llegadas se calculan con una velocidad de referencia
                        de {resumen.kilometros_por_dia} km/día porque todavía no
                        hay recorridos suficientes en el historial. Léelas como
                        un orden de magnitud; se afinan solas conforme se
                        carguen más días.
                    </p>
                </div>
            )}

            <section className="flex flex-col gap-3">
                <h2 className="flex items-center gap-2 text-lg font-medium">
                    <MapPin className="size-4" />
                    Última posición reportada
                </h2>
                <MapaFlota puntos={puntos} />
            </section>

            <section className="flex flex-col gap-3">
                <h2 className="flex items-center gap-2 text-lg font-medium">
                    <PackageCheck className="size-4" />
                    Próximas descargas
                </h2>

                {descargas.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <div className="mb-4 grid size-14 place-items-center bg-muted text-muted-foreground">
                            <Truck className="size-7" />
                        </div>
                        <p className="font-medium">
                            No hay llegadas por estimar
                        </p>
                        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                            Hace falta que las unidades tengan ubicación y
                            destino sobre el corredor.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-6">
                        {descargas.map((grupo) => (
                            <div
                                key={grupo.destino_id}
                                className="overflow-x-auto rounded-xl border"
                            >
                                <div className="flex items-center justify-between border-b bg-muted/40 px-4 py-2.5">
                                    <h3 className="font-medium">
                                        {grupo.destino}
                                    </h3>
                                    <Badge variant="secondary">
                                        {grupo.total}{' '}
                                        {grupo.total === 1
                                            ? 'unidad'
                                            : 'unidades'}
                                    </Badge>
                                </div>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Unidad</TableHead>
                                            <TableHead>Carga</TableHead>
                                            <TableHead>Va por</TableHead>
                                            <TableHead>Conductor</TableHead>
                                            <TableHead>Llega</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {grupo.unidades.map((unidad) => (
                                            <TableRow key={unidad.estado_id}>
                                                <TableCell className="font-medium whitespace-nowrap">
                                                    <Link
                                                        href={flota.unidad(
                                                            unidad.tracto_id,
                                                        )}
                                                        className="underline-offset-4 hover:underline"
                                                    >
                                                        {formatearPlaca(
                                                            unidad.placa,
                                                        )}
                                                    </Link>
                                                    {unidad.carreta && (
                                                        <span className="text-muted-foreground">
                                                            {' / '}
                                                            {formatearPlaca(
                                                                unidad.carreta,
                                                            )}
                                                        </span>
                                                    )}
                                                </TableCell>

                                                <TableCell>
                                                    {unidad.tipo_carga_label ?? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>

                                                <TableCell>
                                                    {unidad.ubicacion ?? '—'}
                                                </TableCell>

                                                <TableCell>
                                                    {unidad.conductor ?? (
                                                        <span className="text-muted-foreground">
                                                            Sin conductor
                                                        </span>
                                                    )}
                                                </TableCell>

                                                <TableCell className="whitespace-nowrap">
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">
                                                            {
                                                                unidad
                                                                    .estimacion
                                                                    .label
                                                            }
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatearFecha(
                                                                unidad.fecha_estimada,
                                                            )}{' '}
                                                            ·{' '}
                                                            {
                                                                unidad
                                                                    .estimacion
                                                                    .kilometros
                                                            }{' '}
                                                            km
                                                        </span>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

FlotaIndex.layout = {
    breadcrumbs: [{ title: 'Flota', href: flota.index().url }],
};
