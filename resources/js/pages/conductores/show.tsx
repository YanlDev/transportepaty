import { Head, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import conductores, {
    show,
} from '@/actions/App/Http/Controllers/ConductorController';
import { AgregarDocumentoConductorDialog } from '@/components/conductores/agregar-documento-dialog';
import { ConductorAsistencia } from '@/components/conductores/conductor-asistencia';
import { ConductorIdentidad } from '@/components/conductores/conductor-identidad';
import { ConductorIndicadores } from '@/components/conductores/conductor-indicadores';
import { ConductorLicencia } from '@/components/conductores/conductor-licencia';
import { ConductorProximosViajes } from '@/components/conductores/conductor-proximos-viajes';
import { ConductorViajes } from '@/components/conductores/conductor-viajes';
import { DocumentoRanuraConductor } from '@/components/conductores/documento-ranura';
import { usePermisos } from '@/hooks/use-permisos';
import type {
    AsistenciaCalendarioAnual,
    Conductor,
    ConductorEstadisticas,
    ConductorViajeItem,
    EnumOption,
    EstadoDocumental,
    RanuraDocumental,
} from '@/types/fleet';

type Props = {
    conductor: Conductor;
    documentacion: EstadoDocumental;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
    /** Null para quien no puede ver asistencia: la sección no se muestra. */
    asistencia: AsistenciaCalendarioAnual | null;
    estadisticas: ConductorEstadisticas;
    viajes: ConductorViajeItem[];
};

export default function ConductorShow({
    conductor,
    documentacion,
    ranuras,
    tiposDocumento,
    asistencia,
    estadisticas,
    viajes: viajesRecientes,
}: Props) {
    const { puedeEditar } = usePermisos();

    const obligatorias = ranuras.filter((ranura) => ranura.obligatorio);
    const sueltas = ranuras.filter((ranura) => !ranura.obligatorio);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Conductores', href: conductores.index().url },
            { title: conductor.nombre_completo, href: show(conductor.id).url },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={conductor.nombre_completo} />

            {/* La columna de documentos es la que manda el ancho: tiene que
                entrar «número · Vence dd/mm/aaaa» sin cortarse. Lo que sobra
                va al medio, que se adapta. */}
            <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[280px_minmax(0,1fr)_380px]">
                <ConductorIdentidad
                    conductor={conductor}
                    puedeEditar={puedeEditar}
                />

                <div className="flex min-w-0 flex-col gap-4">
                    <ConductorIndicadores estadisticas={estadisticas} />

                    <ConductorViajes
                        viajes={viajesRecientes}
                        nombreConductor={conductor.nombre_completo}
                    />

                    {asistencia && (
                        <ConductorAsistencia
                            conductorId={conductor.id}
                            asistencia={asistencia}
                        />
                    )}
                </div>

                <div className="flex flex-col gap-4">
                    <ConductorLicencia conductor={conductor} />

                    <section className="rounded-xl border border-border bg-card">
                        <div className="flex items-center justify-between gap-2 border-b p-4">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <FileText className="size-4 text-muted-foreground" />
                                Documentos
                            </h2>
                            {puedeEditar && (
                                <AgregarDocumentoConductorDialog
                                    conductorId={conductor.id}
                                    tipos={tiposDocumento}
                                />
                            )}
                        </div>

                        <div className="flex flex-col gap-2.5 p-4">
                            {obligatorias.map((ranura) => (
                                <DocumentoRanuraConductor
                                    key={ranura.tipo}
                                    ranura={ranura}
                                    conductorId={conductor.id}
                                    tipos={tiposDocumento}
                                    puedeEditar={puedeEditar}
                                />
                            ))}

                            {sueltas.length > 0 && (
                                <>
                                    <p className="mt-2 text-xs font-medium text-muted-foreground">
                                        Otros papeles
                                    </p>
                                    {sueltas.map((ranura) => (
                                        <DocumentoRanuraConductor
                                            key={
                                                ranura.documento?.id ??
                                                ranura.tipo
                                            }
                                            ranura={ranura}
                                            conductorId={conductor.id}
                                            tipos={tiposDocumento}
                                            puedeEditar={puedeEditar}
                                        />
                                    ))}
                                </>
                            )}

                            {documentacion.faltantes.length > 0 && (
                                <p className="text-xs text-red-700 dark:text-red-400">
                                    Falta cargar:{' '}
                                    {documentacion.faltantes.join(', ')}.
                                </p>
                            )}
                        </div>
                    </section>

                    <ConductorProximosViajes />
                </div>
            </div>
        </div>
    );
}
