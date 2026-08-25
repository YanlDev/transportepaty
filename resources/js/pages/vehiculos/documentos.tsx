import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import vehiculos, {
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { index as documentosIndex } from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
import { Button } from '@/components/ui/button';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { DocumentoRanura } from '@/components/vehiculos/documento-ranura';
import { formatearPlaca } from '@/lib/format';
import type { EnumOption, RanuraDocumental } from '@/types/fleet';

type VehiculoResumen = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    tipo: string;
    tipo_label: string;
};

type Props = {
    vehiculo: VehiculoResumen;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
};

export default function VehiculoDocumentos({
    vehiculo,
    ranuras,
    tiposDocumento,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const esTracto = vehiculo.tipo === 'tracto';

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
            { title: 'Documentos', href: documentosIndex(vehiculo.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Documentos · ${formatearPlaca(vehiculo.placa)}`} />

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
                    <p className="text-sm text-muted-foreground">
                        {vehiculo.tipo_label} · {formatearPlaca(vehiculo.placa)}
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

            <div className="flex flex-col gap-1.5">
                {ranuras.map((ranura) => (
                    <DocumentoRanura
                        key={ranura.documento?.id ?? ranura.tipo}
                        ranura={ranura}
                        vehiculoId={vehiculo.id}
                        tipos={tiposDocumento}
                        puedeGestionar={puedeGestionar}
                    />
                ))}
            </div>
        </div>
    );
}
