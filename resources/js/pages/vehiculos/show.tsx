import { Head, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import vehiculos, {
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { DocumentoFilaVehiculo } from '@/components/vehiculos/documento-fila-vehiculo';
import { DocumentoRanura } from '@/components/vehiculos/documento-ranura';
import { VehiculoFichaTecnica } from '@/components/vehiculos/vehiculo-ficha-tecnica';
import {
    resumenPendientes,
    VehiculoIdentidad,
} from '@/components/vehiculos/vehiculo-identidad';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    EnumOption,
    EstadoDocumental,
    RanuraDocumental,
    Vehiculo,
} from '@/types/fleet';

type Props = {
    vehiculo: Vehiculo;
    documentacion: EstadoDocumental;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
};

/**
 * La ficha del vehículo: identidad y datos generales a la izquierda, y a la
 * derecha lo que se viene a mirar —el expediente documental— con la ficha
 * técnica debajo. Los datos no se repiten entre columnas: lo general vive a
 * la izquierda y lo técnico (motor, VIN, pesos) a la derecha.
 */
export default function VehiculoShow({
    vehiculo,
    documentacion,
    ranuras,
    tiposDocumento,
}: Props) {
    const { puedeEditar } = usePermisos();
    const esTracto = vehiculo.tipo === 'tracto';

    const obligatorias = ranuras.filter((ranura) => ranura.obligatorio);
    const sueltas = ranuras.filter((ranura) => !ranura.obligatorio);
    const ordenadas = [...obligatorias, ...sueltas];

    const pendientes =
        documentacion.faltantes.length +
        documentacion.vencidos.length +
        documentacion.por_vencer.length;

    setLayoutProps({
        breadcrumbs: [
            {
                title: esTracto ? 'Tractos' : 'Carretas',
                href: (esTracto ? vehiculos.tractos() : vehiculos.carretas())
                    .url,
            },
            {
                title: formatearPlaca(vehiculo.placa),
                href: show(vehiculo.id).url,
            },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={formatearPlaca(vehiculo.placa)} />

            <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                <VehiculoIdentidad
                    vehiculo={vehiculo}
                    esTracto={esTracto}
                    puedeEditar={puedeEditar}
                />

                <div className="flex min-w-0 flex-col gap-4">
                    <section className="rounded-xl border border-border bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <FileText className="size-4 text-muted-foreground" />
                                Documentos
                                <span
                                    className={cn(
                                        'font-normal',
                                        pendientes === 0
                                            ? 'text-muted-foreground'
                                            : 'text-red-700 dark:text-red-400',
                                    )}
                                >
                                    {pendientes === 0
                                        ? '· todo al día'
                                        : `· ${resumenPendientes(documentacion)}`}
                                </span>
                            </h2>

                            {puedeEditar && (
                                <AgregarDocumentoDialog
                                    vehiculoId={vehiculo.id}
                                    tipos={tiposDocumento}
                                />
                            )}
                        </div>

                        {/* En el celular la tabla no entra: se cae a las mismas
                            tarjetas del expediente del conductor. */}
                        <div className="flex flex-col gap-2 p-3 sm:hidden">
                            {ordenadas.map((ranura) => (
                                <DocumentoRanura
                                    key={ranura.documento?.id ?? ranura.tipo}
                                    ranura={ranura}
                                    vehiculoId={vehiculo.id}
                                    tipos={tiposDocumento}
                                    puedeEditar={puedeEditar}
                                />
                            ))}
                        </div>

                        <div className="hidden overflow-x-auto sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Documento</TableHead>
                                        <TableHead className="w-32">
                                            Vencimiento
                                        </TableHead>
                                        <TableHead className="w-32">
                                            Estado
                                        </TableHead>
                                        <TableHead className="w-28 text-right">
                                            Acciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ordenadas.map((ranura) => (
                                        <DocumentoFilaVehiculo
                                            key={
                                                ranura.documento?.id ??
                                                ranura.tipo
                                            }
                                            ranura={ranura}
                                            vehiculoId={vehiculo.id}
                                            tipos={tiposDocumento}
                                            puedeEditar={puedeEditar}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <VehiculoFichaTecnica
                        vehiculo={vehiculo}
                        esTracto={esTracto}
                    />
                </div>
            </div>
        </div>
    );
}

/** La columna que identifica al fierro: placa, tipo, estado y datos generales. */

/** Ej: «falta 1 · 2 vencidos». */
