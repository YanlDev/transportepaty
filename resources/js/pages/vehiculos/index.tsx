import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Container, Plus, Truck } from 'lucide-react';
import vehiculos, {
    create,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { Copiable } from '@/components/copiable';
import { EmptyState } from '@/components/empty-state';
import {
    filaSemaforo,
    LeyendaSemaforo,
    ResumenProblemas,
} from '@/components/semaforo-documental';
import { Button } from '@/components/ui/button';
import { Paginacion } from '@/components/ui/paginacion';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { VehiculoFiltros } from '@/components/vehiculos/vehiculo-filtros';
import { VehiculoTarjetaMovil } from '@/components/vehiculos/vehiculo-tarjeta-movil';
import { usePermisos } from '@/hooks/use-permisos';
import type { FiltrosVehiculo } from '@/hooks/use-vehiculo-filtros';
import { formatearPlaca } from '@/lib/format';
import type { EnumOption, Paginator, VehiculoListItem } from '@/types/fleet';

type Seccion = 'tracto' | 'carreta';

type Props = {
    vehiculos: Paginator<VehiculoListItem>;
    filtros: FiltrosVehiculo;
    seccion: Seccion;
    estados: EnumOption[];
    marcas: EnumOption[];
    cajas: EnumOption[];
};

const TEXTOS: Record<
    Seccion,
    {
        titulo: string;
        singular: string;
        plural: string;
        nuevo: string;
        icono: typeof Truck;
    }
> = {
    tracto: {
        titulo: 'Tractos',
        singular: 'tracto registrado',
        plural: 'tractos registrados',
        nuevo: 'Nuevo tracto',
        icono: Truck,
    },
    carreta: {
        titulo: 'Carretas',
        singular: 'carreta registrada',
        plural: 'carretas registradas',
        nuevo: 'Nueva carreta',
        icono: Container,
    },
};

export default function VehiculosIndex({
    vehiculos: paginador,
    filtros,
    seccion,
    estados,
    marcas,
    cajas,
}: Props) {
    const { puedeEditar } = usePermisos();
    const textos = TEXTOS[seccion];
    const url = (
        seccion === 'tracto' ? vehiculos.tractos() : vehiculos.carretas()
    ).url;
    const IconoVacio = textos.icono;

    setLayoutProps({
        breadcrumbs: [{ title: textos.titulo, href: url }],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={textos.titulo} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? textos.singular
                            : textos.plural}
                    </p>
                </div>

                {puedeEditar && (
                    <Button asChild>
                        <Link href={create({ query: { tipo: seccion } })}>
                            <Plus className="size-4" />
                            {textos.nuevo}
                        </Link>
                    </Button>
                )}
            </div>

            <VehiculoFiltros
                filtros={filtros}
                url={url}
                estados={estados}
                marcas={marcas}
                cajas={cajas}
            />

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<IconoVacio className="size-7" />}
                    titulo={`No se encontraron ${textos.plural}`}
                    descripcion={
                        <>
                            Ajusta los filtros de búsqueda
                            {puedeEditar &&
                                ` o registra tu primer${seccion === 'carreta' ? 'a' : ''} ${seccion}`}
                            .
                        </>
                    }
                    accion={
                        puedeEditar && (
                            <Button asChild variant="outline">
                                <Link
                                    href={create({ query: { tipo: seccion } })}
                                >
                                    <Plus className="size-4" />
                                    {textos.nuevo}
                                </Link>
                            </Button>
                        )
                    }
                />
            ) : (
                <>
                    <LeyendaSemaforo />

                    <div className="flex flex-col gap-2 sm:hidden">
                        {paginador.data.map((vehiculo) => (
                            <VehiculoTarjetaMovil
                                key={vehiculo.id}
                                vehiculo={vehiculo}
                            />
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto rounded-xl border shadow-sm sm:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Placa</TableHead>
                                    <TableHead>TUC</TableHead>
                                    <TableHead>Marca</TableHead>
                                    <TableHead className="text-right">
                                        Año
                                    </TableHead>
                                    {seccion === 'tracto' && (
                                        <TableHead>Caja</TableHead>
                                    )}
                                    <TableHead>Color</TableHead>
                                    <TableHead>Documentación</TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((vehiculo) => (
                                    <TableRow
                                        key={vehiculo.id}
                                        onClick={(evento) =>
                                            irAlVehiculo(evento, vehiculo.id)
                                        }
                                        className={`group/fila cursor-pointer ${
                                            filaSemaforo[
                                                vehiculo.documentacion.semaforo
                                            ]
                                        }`}
                                    >
                                        <TableCell className="font-medium">
                                            <Copiable
                                                valor={formatearPlaca(
                                                    vehiculo.placa,
                                                )}
                                                etiqueta="placa"
                                            >
                                                <Link
                                                    href={show(vehiculo.id)}
                                                    className="hover:underline"
                                                >
                                                    {formatearPlaca(
                                                        vehiculo.placa,
                                                    )}
                                                </Link>
                                            </Copiable>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            <Copiable
                                                valor={vehiculo.tuc_numero}
                                                etiqueta="TUC"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {vehiculo.marca ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {vehiculo.anio ?? '—'}
                                        </TableCell>
                                        {seccion === 'tracto' && (
                                            <TableCell className="text-muted-foreground">
                                                {vehiculo.caja_label ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-muted-foreground">
                                            {vehiculo.color ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <ResumenProblemas
                                                estado={vehiculo.documentacion}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <EstadoBadge
                                                estado={vehiculo.estado}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}

/**
 * Lleva al detalle del vehículo al hacer clic en cualquier parte de la fila.
 *
 * Se abstiene cuando el clic cayó sobre algo interactivo —el enlace de la placa,
 * los botones de copiar, los chips de documentación— porque esos elementos ya
 * tienen su propia acción. También respeta la selección de texto: si el usuario
 * estaba marcando una placa para copiarla a mano, soltar el mouse no debe
 * cambiar de página.
 */
function irAlVehiculo(
    evento: React.MouseEvent<HTMLTableRowElement>,
    vehiculoId: number,
) {
    const objetivo = evento.target as HTMLElement;

    if (objetivo.closest('a, button, [role="button"]')) {
        return;
    }

    if (window.getSelection()?.toString()) {
        return;
    }

    router.visit(show(vehiculoId).url);
}
