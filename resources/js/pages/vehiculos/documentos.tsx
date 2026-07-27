import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import vehiculos, {
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { index as documentosIndex } from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { DocumentoItem } from '@/components/vehiculos/documento-item';
import type { EnumOption, VehiculoDocumentoItem } from '@/types/fleet';

type VehiculoResumen = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    tipo_label: string;
};

type Props = {
    vehiculo: VehiculoResumen;
    documentos: VehiculoDocumentoItem[];
    tiposDocumento: EnumOption[];
};

export default function VehiculoDocumentos({
    vehiculo,
    documentos,
    tiposDocumento,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos.index().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
            { title: 'Documentos', href: documentosIndex(vehiculo.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Documentos · ${vehiculo.placa}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mb-2 -ml-2"
                    >
                        <Link href={show(vehiculo.id)}>
                            <ArrowLeft className="size-4" />
                            Volver al vehículo
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Documentación
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {vehiculo.tipo_label} · {vehiculo.placa}
                        {vehiculo.marca && ` · ${vehiculo.marca}`}
                        {vehiculo.modelo && ` ${vehiculo.modelo}`}
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
                    className="rounded-xl py-16"
                />
            ) : (
                <div className="flex flex-col gap-2.5">
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
        </div>
    );
}
