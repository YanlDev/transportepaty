import { Head } from '@inertiajs/react';
import { Car, Fuel } from 'lucide-react';
import { rapido } from '@/actions/App/Http/Controllers/CargaCombustibleController';
import { RegistrarCargaDialog } from '@/components/combustible/registrar-carga-dialog';
import { EmptyState } from '@/components/empty-state';

type VehiculoItem = {
    id: number;
    placa: string;
    marca: string;
    modelo: string;
    foto: string | null;
};

type Props = {
    vehiculos: VehiculoItem[];
};

export default function RegistrarCargaRapido({ vehiculos }: Props) {
    return (
        <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-4 md:p-6">
            <Head title="Registrar carga" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Registrar carga
                </h1>
                <p className="text-sm text-muted-foreground">
                    Elige tu vehículo y sube las fotos del comprobante y el
                    odómetro.
                </p>
            </div>

            {vehiculos.length === 0 ? (
                <EmptyState
                    icon={<Fuel className="size-6" />}
                    text="No tienes vehículos asignados. Pídele a un administrador que te asigne uno."
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {vehiculos.map((vehiculo) => (
                        <li
                            key={vehiculo.id}
                            className="flex items-center gap-4 rounded-xl border border-border bg-card p-4"
                        >
                            <span className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-muted text-muted-foreground">
                                {vehiculo.foto ? (
                                    <img
                                        src={vehiculo.foto}
                                        alt={vehiculo.placa}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Car className="size-5" />
                                )}
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="font-mono text-sm font-semibold text-foreground">
                                    {vehiculo.placa}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {vehiculo.marca} {vehiculo.modelo}
                                </p>
                            </div>

                            <RegistrarCargaDialog vehiculoId={vehiculo.id} />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

RegistrarCargaRapido.layout = {
    breadcrumbs: [{ title: 'Registrar carga', href: rapido().url }],
};
