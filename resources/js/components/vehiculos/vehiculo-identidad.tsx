import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    Palette,
    Pencil,
    Settings2,
    Trash2,
    Truck,
    Wrench,
} from 'lucide-react';
import { edit } from '@/actions/App/Http/Controllers/VehiculoController';
import { Button } from '@/components/ui/button';
import { DeleteVehiculoDialog } from '@/components/vehiculos/delete-vehiculo-dialog';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { EstadoDocumental, Vehiculo } from '@/types/fleet';
import { cajaLabels, tipoLabels } from '@/types/fleet';

export function VehiculoIdentidad({
    vehiculo,
    esTracto,
    puedeEditar,
}: {
    vehiculo: Vehiculo;
    esTracto: boolean;
    puedeEditar: boolean;
}) {
    return (
        <section className="flex flex-col gap-5 rounded-xl border border-border bg-card p-5 lg:sticky lg:top-4 lg:self-start">
            <div className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {formatearPlaca(vehiculo.placa)}
                    </h1>
                    <EstadoBadge estado={vehiculo.estado} />
                </div>

                <div className="flex flex-wrap gap-2">
                    <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                        {tipoLabels[vehiculo.tipo] ?? vehiculo.tipo}
                    </span>
                    {vehiculo.anio && (
                        <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium tabular-nums">
                            {vehiculo.anio}
                        </span>
                    )}
                </div>

                {puedeEditar && (
                    <div className="flex gap-2">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="flex-1"
                        >
                            <Link href={edit(vehiculo.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                        <DeleteVehiculoDialog
                            vehiculo={vehiculo}
                            trigger={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-muted-foreground hover:text-destructive"
                                    aria-label="Eliminar vehículo"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            }
                        />
                    </div>
                )}
            </div>

            <ul className="flex flex-col gap-2.5 border-t pt-4 text-sm">
                <DatoLateral icono={Truck} label="Marca">
                    {vehiculo.marca ?? '—'}
                </DatoLateral>
                <DatoLateral icono={Settings2} label="Modelo">
                    {vehiculo.modelo ?? '—'}
                </DatoLateral>
                <DatoLateral icono={Palette} label="Color">
                    {vehiculo.color ?? '—'}
                </DatoLateral>
                {esTracto && (
                    <DatoLateral icono={Wrench} label="Caja">
                        {vehiculo.caja
                            ? (cajaLabels[vehiculo.caja] ?? vehiculo.caja)
                            : '—'}
                    </DatoLateral>
                )}
                <DatoLateral icono={CalendarDays} label="Adquisición">
                    {vehiculo.fecha_adquisicion
                        ? formatearFecha(vehiculo.fecha_adquisicion)
                        : '—'}
                </DatoLateral>
            </ul>

            {vehiculo.observaciones && (
                <div className="border-t pt-4">
                    <p className="text-xs text-muted-foreground">
                        Observaciones
                    </p>
                    <p className="mt-1 text-sm whitespace-pre-line">
                        {vehiculo.observaciones}
                    </p>
                </div>
            )}
        </section>
    );
}

function DatoLateral({
    icono: Icono,
    label,
    children,
}: {
    icono: typeof Truck;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <li className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-2.5 text-muted-foreground">
                <Icono className="size-4 shrink-0" />
                {label}
            </span>
            <span className="min-w-0 truncate font-medium">{children}</span>
        </li>
    );
}

export function resumenPendientes(documentacion: EstadoDocumental): string {
    const partes: string[] = [];

    if (documentacion.faltantes.length > 0) {
        partes.push(
            `falta${documentacion.faltantes.length > 1 ? 'n' : ''} ${documentacion.faltantes.length}`,
        );
    }

    if (documentacion.vencidos.length > 0) {
        partes.push(
            `${documentacion.vencidos.length} vencido${documentacion.vencidos.length > 1 ? 's' : ''}`,
        );
    }

    if (documentacion.por_vencer.length > 0) {
        partes.push(`${documentacion.por_vencer.length} por vencer`);
    }

    return partes.join(' · ');
}
