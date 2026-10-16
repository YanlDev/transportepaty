import { Head, setLayoutProps } from '@inertiajs/react';
import conductores, {
    show,
} from '@/actions/App/Http/Controllers/ConductorController';
import { ConductorAsistencia } from '@/components/conductores/conductor-asistencia';
import { ConductorDocumentos } from '@/components/conductores/conductor-documentos';
import { ConductorIdentidad } from '@/components/conductores/conductor-identidad';
import { ConductorIndicadores } from '@/components/conductores/conductor-indicadores';
import { ConductorResumenDocumentos } from '@/components/conductores/conductor-resumen-documentos';
import { ConductorViajes } from '@/components/conductores/conductor-viajes';
import { usePermisos } from '@/hooks/use-permisos';
import type {
    AsistenciaCalendarioAnual,
    Conductor,
    ConductorEstadisticas,
    ConductorViajeItem,
    EnumOption,
    RanuraDocumental,
} from '@/types/fleet';

type Props = {
    conductor: Conductor;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
    /** Null para quien no puede ver asistencia: la sección no se muestra. */
    asistencia: AsistenciaCalendarioAnual | null;
    estadisticas: ConductorEstadisticas;
    viajes: ConductorViajeItem[];
};

/**
 * La ficha del conductor, todo a lo ancho: quién es arriba, y debajo lo que se
 * viene a revisar —sus documentos, sus viajes y su asistencia—. Los datos de
 * la licencia ya están en la cabecera y en el expediente, así que no tienen
 * tarjeta propia.
 */
export default function ConductorShow({
    conductor,
    ranuras,
    tiposDocumento,
    asistencia,
    estadisticas,
    viajes: viajesRecientes,
}: Props) {
    const { puede } = usePermisos();
    const puedeEditar = puede('conductores.editar');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Conductores', href: conductores.index().url },
            { title: conductor.nombre_completo, href: show(conductor.id).url },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={conductor.nombre_completo} />

            <ConductorIdentidad
                conductor={conductor}
                puedeEditar={puedeEditar}
            />

            <ConductorIndicadores estadisticas={estadisticas} />

            <ConductorDocumentos
                conductorId={conductor.id}
                ranuras={ranuras}
                tipos={tiposDocumento}
                puedeEditar={puedeEditar}
            />

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
                <ConductorViajes
                    viajes={viajesRecientes}
                    nombreConductor={conductor.nombre_completo}
                />
                <ConductorResumenDocumentos ranuras={ranuras} />
            </div>

            {asistencia && (
                <ConductorAsistencia
                    conductorId={conductor.id}
                    asistencia={asistencia}
                />
            )}
        </div>
    );
}
