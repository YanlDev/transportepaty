import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { FileText, Pencil, Trash2 } from 'lucide-react';
import vehiculos, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { DeleteVehiculoDialog } from '@/components/vehiculos/delete-vehiculo-dialog';
import { DocumentoItem } from '@/components/vehiculos/documento-item';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { Dato, InfoCard } from '@/components/vehiculos/info-card';
import { formatearFecha } from '@/lib/format';
import type {
    EnumOption,
    Vehiculo,
    VehiculoDocumentoItem,
} from '@/types/fleet';
import { cajaLabels, tipoLabels } from '@/types/fleet';

type Props = {
    vehiculo: Vehiculo;
    documentos: VehiculoDocumentoItem[];
    documentosTotal: number;
    tiposDocumento: EnumOption[];
};

export default function VehiculoShow({
    vehiculo,
    documentos,
    tiposDocumento,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const esTracto = vehiculo.tipo === 'tracto';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos.index().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={vehiculo.placa} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {vehiculo.placa}
                        </h1>
                        <EstadoBadge estado={vehiculo.estado} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {tipoLabels[vehiculo.tipo] ?? vehiculo.tipo}
                        {vehiculo.marca && ` · ${vehiculo.marca}`}
                        {vehiculo.modelo && ` ${vehiculo.modelo}`}
                    </p>
                </div>

                {puedeGestionar && (
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline">
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
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="size-4" />
                                    Eliminar
                                </Button>
                            }
                        />
                    </div>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <InfoCard title="Ficha técnica">
                    <dl className="grid grid-cols-2 gap-4">
                        <Dato
                            label="Tipo"
                            value={tipoLabels[vehiculo.tipo] ?? vehiculo.tipo}
                        />
                        <Dato label="Marca" value={vehiculo.marca ?? '—'} />
                        <Dato label="Modelo" value={vehiculo.modelo ?? '—'} />
                        <Dato
                            label="Año"
                            value={vehiculo.anio ? String(vehiculo.anio) : '—'}
                        />
                        <Dato label="Color" value={vehiculo.color ?? '—'} />
                        {esTracto && (
                            <Dato
                                label="Caja"
                                value={
                                    vehiculo.caja
                                        ? (cajaLabels[vehiculo.caja] ??
                                          vehiculo.caja)
                                        : '—'
                                }
                            />
                        )}
                        <Dato
                            label="Ejes"
                            value={vehiculo.ejes ? String(vehiculo.ejes) : '—'}
                        />
                        <Dato label="VIN" value={vehiculo.vin ?? '—'} />
                        {esTracto && (
                            <Dato
                                label="N.° de motor"
                                value={vehiculo.numero_motor ?? '—'}
                            />
                        )}
                        <Dato
                            label="Fecha de adquisición"
                            value={
                                vehiculo.fecha_adquisicion
                                    ? formatearFecha(vehiculo.fecha_adquisicion)
                                    : '—'
                            }
                        />
                    </dl>
                </InfoCard>

                <InfoCard title="Pesos">
                    <dl className="grid grid-cols-2 gap-4">
                        <Dato
                            label="Peso neto"
                            value={formatearKg(vehiculo.peso_neto)}
                        />
                        <Dato
                            label="Peso bruto"
                            value={formatearKg(vehiculo.peso_bruto)}
                        />
                        <Dato
                            label="Carga útil"
                            value={formatearKg(vehiculo.carga_util)}
                        />
                    </dl>

                    {vehiculo.observaciones && (
                        <div className="mt-5 border-t pt-4">
                            <p className="text-xs text-muted-foreground">
                                Observaciones
                            </p>
                            <p className="mt-1 text-sm whitespace-pre-line">
                                {vehiculo.observaciones}
                            </p>
                        </div>
                    )}
                </InfoCard>
            </div>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold text-foreground">
                            Documentos
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {esTracto
                                ? 'SOAT, revisión técnica de mercancías, TUC, MATPEL y tarjeta de propiedad.'
                                : 'Revisión técnica de mercancías, TUC, MATPEL y tarjeta de propiedad (la carreta no lleva SOAT).'}
                        </p>
                    </div>

                    {puedeGestionar && (
                        <AgregarDocumentoDialog
                            vehiculoId={vehiculo.id}
                            tipos={tiposDocumento}
                        />
                    )}
                </div>

                {documentos.length === 0 ? (
                    <EmptyState
                        icon={<FileText className="size-6" />}
                        text="Este vehículo aún no tiene documentos."
                        className="rounded-xl py-12"
                    />
                ) : (
                    <div className="grid gap-2">
                        {documentos.map((documento) => (
                            <DocumentoItem
                                key={documento.id}
                                documento={documento}
                                vehiculoId={vehiculo.id}
                                puedeGestionar={puedeGestionar}
                            />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

function formatearKg(valor: number | null): string {
    return valor === null ? '—' : `${valor.toLocaleString('es-PE')} kg`;
}
