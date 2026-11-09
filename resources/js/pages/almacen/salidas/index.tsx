import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowUpFromLine, Plus } from 'lucide-react';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { AlmacenNav, migasAlmacen } from '@/components/almacen/almacen-nav';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { FiltroSelect } from '@/components/filtro-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFiltros } from '@/hooks/use-filtros';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SalidaCabecera, VehiculoElegible } from '@/types/almacen';
import type { EnumOption, Paginator } from '@/types/fleet';

type Props = {
    salidas: Paginator<SalidaCabecera>;
    filtros: { buscar: string; destino: string; vehiculo: string };
    destinos: EnumOption[];
    vehiculos: VehiculoElegible[];
};

/** Todo lo que salió del almacén, a qué unidad u orden fue y cuánto costó. */
export default function SalidasIndex({
    salidas: paginador,
    filtros,
    destinos,
    vehiculos,
}: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const { buscar, setBuscar, aplicar } = useFiltros(
        filtros,
        salidas.index().url,
        { conservar: ['destinos', 'vehiculos'] },
    );

    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: 'Vales de salida',
            href: salidas.index().url,
        }),
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Vales de salida" />

            <EncabezadoPantalla
                titulo="Vales de salida"
                descripcion="Nada sale del almacén sin vale: cada uno dice a qué unidad u orden de trabajo fue."
                icono={ArrowUpFromLine}
                acciones={
                    puede('almacen.despachar') && (
                        <Button asChild className="max-md:hidden">
                            <Link href={salidas.create()}>
                                <Plus className="size-4" />
                                Nuevo vale
                            </Link>
                        </Button>
                    )
                }
            />

            <AlmacenNav actual="salidas" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar por N°, artículo o quién recibió..."
                    className="h-11 sm:max-w-sm md:h-9"
                />
                <FiltroSelect
                    valor={filtros.destino || null}
                    onCambio={(valor) => aplicar({ destino: valor ?? '' })}
                    todos="Todos los destinos"
                    etiqueta="Destino"
                    opciones={destinos}
                />
                <FiltroSelect
                    valor={filtros.vehiculo || null}
                    onCambio={(valor) => aplicar({ vehiculo: valor ?? '' })}
                    todos="Todas las unidades"
                    etiqueta="Unidad"
                    opciones={vehiculos.map((v) => ({
                        value: String(v.id),
                        label: formatearPlaca(v.placa),
                    }))}
                />
            </div>

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<ArrowUpFromLine className="size-7" />}
                    titulo="No hay vales"
                    descripcion="Cada entrega al taller se registra con un vale."
                />
            ) : (
                <>
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>N°</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Destino</TableHead>
                                    <TableHead>Recibió</TableHead>
                                    <TableHead className="text-right">
                                        Líneas
                                    </TableHead>
                                    {veCostos && (
                                        <TableHead className="text-right">
                                            Costo
                                        </TableHead>
                                    )}
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((salida) => (
                                    <TableRow
                                        key={salida.id}
                                        className={cn(
                                            'cursor-pointer',
                                            salida.anulado && 'opacity-60',
                                        )}
                                        onClick={() =>
                                            router.visit(
                                                salidas.show(salida.id),
                                            )
                                        }
                                    >
                                        <TableCell className="font-mono text-xs">
                                            <Link
                                                href={salidas.show(salida.id)}
                                                className="hover:underline"
                                            >
                                                {salida.numero}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {formatearFecha(salida.fecha)}
                                        </TableCell>
                                        <TableCell>
                                            {salida.destino_label}
                                            {salida.placa &&
                                                ` · ${formatearPlaca(salida.placa)}`}
                                            {salida.orden_trabajo_id &&
                                                ` · OT ${salida.orden_trabajo_id}`}
                                        </TableCell>
                                        <TableCell>
                                            {salida.entregado_a ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {salida.lineas}
                                        </TableCell>
                                        {veCostos && (
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatearSoles(
                                                    salida.costo_total,
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            {salida.anulado && (
                                                <EstadoDocumento tono="anulado">
                                                    Anulado
                                                </EstadoDocumento>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <Paginacion paginador={paginador} />
                </>
            )}

            {puede('almacen.despachar') && (
                <AccionPrincipalMovil href={salidas.create()} icono={<Plus />}>
                    Nuevo vale
                </AccionPrincipalMovil>
            )}
        </div>
    );
}
