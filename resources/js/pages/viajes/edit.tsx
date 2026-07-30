import { Head, usePage } from '@inertiajs/react';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { GuiasPanel } from '@/components/viajes/guias-panel';
import { ViajeForm } from '@/components/viajes/viaje-form';
import { formatearPlaca } from '@/lib/format';
import type { GuiaRemision, ViajeEditable, ViajeOpciones } from '@/types/fleet';

type Props = {
    viaje: ViajeEditable;
    guias: GuiaRemision[];
    opciones: ViajeOpciones;
};

export default function ViajesEdit({ viaje, guias, opciones }: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    return (
        <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
            <Head title={`Viaje ${viaje.tracto_placa}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Viaje de {formatearPlaca(viaje.tracto_placa)}
                </h1>
                <p className="text-sm text-muted-foreground">
                    Salió el {viaje.fecha_salida}
                    {viaje.fecha_llegada
                        ? ` y llegó el ${viaje.fecha_llegada}`
                        : ' y sigue en curso'}
                </p>
            </div>

            <section className="flex flex-col gap-3">
                <h2 className="text-lg font-medium">Guías de remisión</h2>
                <GuiasPanel
                    viajeId={viaje.id}
                    guias={guias}
                    puedeGestionar={puedeGestionar}
                />
            </section>

            {puedeGestionar && (
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Datos del viaje</h2>
                    <ViajeForm viaje={viaje} opciones={opciones} />
                </section>
            )}
        </div>
    );
}

ViajesEdit.layout = {
    breadcrumbs: [{ title: 'Viajes', href: viajes.index().url }],
};
