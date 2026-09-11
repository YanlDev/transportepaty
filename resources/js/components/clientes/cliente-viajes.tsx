import { Link } from '@inertiajs/react';
import { ArrowRight, Eye, Truck } from 'lucide-react';
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
import { ConductorCelda } from '@/components/viajes/conductor-celda';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { formatearFecha, formatearPeso, formatearPlaca } from '@/lib/format';
import type { ClienteViajeItem } from '@/types/fleet';

type Props = {
    viajes: ClienteViajeItem[];
    /** Razón social: con eso se filtra el listado general al «Ver todos». */
    cliente: string;
};

/**
 * Los últimos viajes del cliente. El historial completo no vive acá: «Ver
 * todos» va al listado general ya filtrado, que es donde están los filtros y
 * el detalle de cada GR.
 */
export function ClienteViajes({ viajes: lista, cliente }: Props) {
    return (
        <section className="min-w-0 rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Truck className="size-4 text-muted-foreground" />
                    Últimos viajes
                </h2>
                {lista.length > 0 && (
                    <Button asChild variant="ghost" size="sm">
                        <Link href={viajes.index({ query: { cliente } }).url}>
                            Ver todos
                        </Link>
                    </Button>
                )}
            </div>

            {lista.length === 0 ? (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    Este cliente todavía no tiene viajes registrados.
                </p>
            ) : (
                <>
                    {/* En el celular la tabla no entra: cada viaje pasa a ser
                        una tarjeta con lo que se mira de un vistazo. */}
                    <div className="flex flex-col divide-y lg:hidden">
                        {lista.map((viaje) => (
                            <ViajeTarjeta key={viaje.id} viaje={viaje} />
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto lg:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-24">
                                        Fecha
                                    </TableHead>
                                    <TableHead className="w-32">
                                        N.º GR
                                    </TableHead>
                                    <TableHead className="w-36">
                                        Unidad
                                    </TableHead>
                                    <TableHead>Conductor</TableHead>
                                    <TableHead className="w-40">Ruta</TableHead>
                                    <TableHead className="w-32">
                                        Carga
                                    </TableHead>
                                    <TableHead className="w-24 text-right">
                                        Peso
                                    </TableHead>
                                    <TableHead className="w-12" />
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
                                            {viaje.numero_gr}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs whitespace-nowrap">
                                            {unidad(viaje)}
                                        </TableCell>
                                        <TableCell className="max-w-0 truncate">
                                            <ConductorCelda
                                                nombre={viaje.conductor_nombre}
                                                conductorId={viaje.conductor_id}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Ruta viaje={viaje} />
                                        </TableCell>
                                        <TableCell>
                                            <TipoCargaBadge
                                                valor={viaje.tipo_carga}
                                                label={viaje.tipo_carga_label}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                            {formatearPeso(
                                                viaje.peso,
                                                viaje.unidad_peso,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <VerGuia viaje={viaje} />
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

/** «ABC-123 / XYZ-789» cuando lleva carreta, solo el tracto cuando no. */
function unidad(viaje: ClienteViajeItem): string {
    const tracto = formatearPlaca(viaje.placa_tracto);

    return viaje.placa_carreta
        ? `${tracto} / ${formatearPlaca(viaje.placa_carreta)}`
        : tracto;
}

function Ruta({ viaje }: { viaje: ClienteViajeItem }) {
    return (
        <span className="flex items-center gap-1 text-xs text-muted-foreground">
            <span className="truncate">{viaje.origen_ciudad}</span>
            <ArrowRight className="size-3 shrink-0" />
            <span className="truncate">{viaje.destino_ciudad}</span>
        </span>
    );
}

function VerGuia({ viaje }: { viaje: ClienteViajeItem }) {
    if (!viaje.archivo_url) {
        return null;
    }

    return (
        <a
            href={viaje.archivo_url}
            target="_blank"
            rel="noreferrer"
            className="inline-grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            aria-label={`Ver GR ${viaje.numero_gr}`}
        >
            <Eye className="size-4" />
        </a>
    );
}

function ViajeTarjeta({ viaje }: { viaje: ClienteViajeItem }) {
    return (
        <div className="flex flex-col gap-2 p-3">
            <div className="flex items-start justify-between gap-2">
                <span className="text-sm font-semibold tabular-nums">
                    {formatearFecha(viaje.fecha_traslado)}
                </span>
                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                    {formatearPeso(viaje.peso, viaje.unidad_peso)}
                </span>
            </div>
            <Ruta viaje={viaje} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-mono text-xs text-muted-foreground">
                    {formatearPlaca(viaje.placa_tracto)}
                </span>
                <TipoCargaBadge
                    valor={viaje.tipo_carga}
                    label={viaje.tipo_carga_label}
                />
            </div>
        </div>
    );
}
