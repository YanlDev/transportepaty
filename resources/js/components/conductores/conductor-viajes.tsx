import { Link } from '@inertiajs/react';
import { Eye, Route as RouteIcon } from 'lucide-react';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { ConductorViajeItem } from '@/types/fleet';

/**
 * Los últimos viajes que manejó. El historial completo no vive acá: «Ver
 * todos» va al listado general ya filtrado por su nombre, que es donde están
 * los filtros y el detalle de cada GR.
 */
export function ConductorViajes({
    viajes: lista,
    nombreConductor,
}: {
    viajes: ConductorViajeItem[];
    nombreConductor: string;
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-3">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <RouteIcon className="size-4 text-muted-foreground" />
                    Viajes recientes
                </h2>
                {lista.length > 0 && (
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={
                                viajes.index({
                                    query: { buscar: nombreConductor },
                                }).url
                            }
                        >
                            Ver todos
                        </Link>
                    </Button>
                )}
            </div>

            {lista.length === 0 ? (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    Este conductor todavía no tiene viajes registrados.
                </p>
            ) : (
                <>
                    <div className="flex flex-col divide-y sm:hidden">
                        {lista.map((viaje) => (
                            <div
                                key={viaje.id}
                                className="flex flex-col gap-2 p-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span className="text-sm font-semibold tabular-nums">
                                        {formatearFecha(viaje.fecha_traslado)}
                                    </span>
                                    <span className="shrink-0 font-mono text-xs text-muted-foreground">
                                        {formatearPlaca(viaje.placa_tracto)}
                                        {viaje.placa_carreta &&
                                            ` / ${formatearPlaca(viaje.placa_carreta)}`}
                                    </span>
                                </div>
                                <ClienteChip cliente={viaje.cliente} />
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <TipoCargaBadge
                                        valor={viaje.tipo_carga}
                                        label={viaje.tipo_carga_label}
                                    />
                                    <GuiaRemision viaje={viaje} />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto sm:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-28">
                                        Fecha
                                    </TableHead>
                                    <TableHead className="w-40">
                                        Unidad
                                    </TableHead>
                                    {/* La única columna elástica: absorbe el
                                        ancho sobrante y trunca los nombres
                                        largos en vez de estirar la tabla. */}
                                    <TableHead>Cliente</TableHead>
                                    <TableHead className="w-36">
                                        Carga
                                    </TableHead>
                                    <TableHead className="w-36">GR</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lista.map((viaje) => (
                                    <TableRow key={viaje.id}>
                                        <TableCell className="whitespace-nowrap tabular-nums">
                                            {formatearFecha(
                                                viaje.fecha_traslado,
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs whitespace-nowrap">
                                            {formatearPlaca(viaje.placa_tracto)}
                                            {viaje.placa_carreta &&
                                                ` / ${formatearPlaca(viaje.placa_carreta)}`}
                                        </TableCell>
                                        <TableCell className="max-w-0">
                                            <ClienteChip
                                                cliente={viaje.cliente}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <TipoCargaBadge
                                                valor={viaje.tipo_carga}
                                                label={viaje.tipo_carga_label}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            <GuiaRemision viaje={viaje} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </>
            )}
        </section>
    );
}

/**
 * El N° de GR del viaje, enlazado a su PDF cuando quedó adjunto. Es el dato
 * con el que se rastrea un viaje contra SUNAT, así que va en el historial
 * aunque el resto de columnas se hayan recortado.
 */
function GuiaRemision({ viaje }: { viaje: ConductorViajeItem }) {
    if (!viaje.archivo_url) {
        return <span className="font-mono text-xs">{viaje.numero_gr}</span>;
    }

    return (
        <a
            href={viaje.archivo_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1.5 font-mono text-xs hover:underline"
        >
            {viaje.numero_gr}
            <Eye className="size-3.5 shrink-0 text-muted-foreground" />
        </a>
    );
}
