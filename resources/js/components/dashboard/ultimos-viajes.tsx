import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
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

/** Las últimas GR que entraron, para confirmar de un vistazo que se están subiendo. */
export function UltimosViajes({
    viajes: lista,
}: {
    viajes: ConductorViajeItem[];
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="text-sm font-semibold">
                    Últimos viajes registrados
                </h2>
                <Button asChild variant="ghost" size="sm">
                    <Link href={viajes.index()}>Ver todos</Link>
                </Button>
            </div>

            {lista.length === 0 ? (
                <p className="p-6 text-center text-sm text-muted-foreground">
                    Todavía no se ha registrado ningún viaje.
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
                                    </span>
                                </div>
                                <ClienteChip cliente={viaje.cliente} />
                                <TipoCargaBadge
                                    valor={viaje.tipo_carga}
                                    label={viaje.tipo_carga_label}
                                />
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
                                    <TableHead>Cliente</TableHead>
                                    <TableHead className="w-36">
                                        Carga
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
                                        <TableCell className="text-right">
                                            {viaje.archivo_url && (
                                                <a
                                                    href={viaje.archivo_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                    aria-label={`Ver GR ${viaje.numero_gr}`}
                                                >
                                                    <Eye className="size-4" />
                                                </a>
                                            )}
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
