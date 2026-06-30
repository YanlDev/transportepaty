import { Head } from '@inertiajs/react';
import { AlertTriangle, Satellite } from 'lucide-react';
import tracksolid from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import { EmptyState } from '@/components/empty-state';
import { DispositivoGpsCard } from '@/components/integraciones/dispositivo-gps-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type {
    DispositivoGps,
    SucursalOption,
    VehiculoDisponible,
} from '@/types/fleet';

type Props = {
    dispositivos: DispositivoGps[];
    error: string | null;
    vehiculosDisponibles: VehiculoDisponible[];
    sucursales: SucursalOption[];
};

export default function TracksolidIntegracion({
    dispositivos,
    error,
    vehiculosDisponibles,
    sucursales,
}: Props) {
    const vinculados = dispositivos.filter((d) => d.vehiculo !== null).length;

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <Head title="Dispositivos GPS" />

            <div className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Dispositivos GPS
                </h1>
                <p className="text-sm text-muted-foreground">
                    Equipos Tracksolid de la cuenta. Vincula cada dispositivo a
                    un vehículo o impórtalo como uno nuevo.
                    {dispositivos.length > 0 && (
                        <>
                            {' '}
                            {vinculados} de {dispositivos.length} vinculados.
                        </>
                    )}
                </p>
            </div>

            {error && (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Error de conexión</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {dispositivos.length === 0 && !error ? (
                <EmptyState
                    icon={<Satellite className="size-6" />}
                    text="No se encontraron dispositivos en la cuenta de Tracksolid."
                />
            ) : (
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {dispositivos.map((dispositivo) => (
                        <DispositivoGpsCard
                            key={dispositivo.imei}
                            dispositivo={dispositivo}
                            vehiculosDisponibles={vehiculosDisponibles}
                            sucursales={sucursales}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

TracksolidIntegracion.layout = {
    breadcrumbs: [{ title: 'Dispositivos GPS', href: tracksolid.index().url }],
};
