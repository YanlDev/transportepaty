import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { CalendarCheck, Pencil } from 'lucide-react';
import { show as mostrarAsistencia } from '@/actions/App/Http/Controllers/AsistenciaController';
import conductores, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/ConductorController';
import { AgregarDocumentoConductorDialog } from '@/components/conductores/agregar-documento-dialog';
import { DocumentoRanuraConductor } from '@/components/conductores/documento-ranura';
import { DocumentosResumen } from '@/components/semaforo-documental';
import { Button } from '@/components/ui/button';
import { Dato } from '@/components/vehiculos/info-card';
import { formatearFecha } from '@/lib/format';
import type {
    Conductor,
    EnumOption,
    EstadoDocumental,
    RanuraDocumental,
} from '@/types/fleet';

type Props = {
    conductor: Conductor;
    documentacion: EstadoDocumental;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
};

export default function ConductorShow({
    conductor,
    documentacion,
    ranuras,
    tiposDocumento,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    const obligatorias = ranuras.filter((ranura) => ranura.obligatorio);
    const sueltas = ranuras.filter((ranura) => !ranura.obligatorio);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Conductores', href: conductores.index().url },
            { title: conductor.nombre_completo, href: show(conductor.id).url },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={conductor.nombre_completo} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-baseline gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {conductor.nombre_completo}
                    </h1>
                    <span className="text-sm text-muted-foreground">
                        DNI {conductor.documento}
                    </span>
                    <span
                        className={
                            conductor.activo
                                ? 'bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-600/20'
                                : 'bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                        }
                    >
                        {conductor.activo ? 'Activo' : 'Inactivo'}
                    </span>
                </div>

                {puedeGestionar && (
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={mostrarAsistencia(conductor.id)}>
                                <CalendarCheck className="size-4" />
                                Ver asistencia
                            </Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={edit(conductor.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    </div>
                )}
            </div>

            <DocumentosResumen estado={documentacion} />

            <section className="border border-border bg-card p-5">
                <h2 className="mb-4 text-sm font-semibold text-foreground">
                    Datos del conductor
                </h2>
                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Dato label="Celular" value={conductor.telefono ?? '—'} />
                    <Dato
                        label="Procedencia"
                        value={conductor.procedencia ?? '—'}
                    />
                    <Dato
                        label="Fecha de nacimiento"
                        value={
                            conductor.fecha_nacimiento
                                ? formatearFecha(conductor.fecha_nacimiento)
                                : '—'
                        }
                    />
                    <Dato label="Correo" value={conductor.email ?? '—'} />
                    <Dato
                        label="N.° de licencia"
                        value={conductor.licencia ?? '—'}
                    />
                    <Dato
                        label="Categoría"
                        value={conductor.categoria_licencia ?? '—'}
                    />
                    <Dato
                        label="Revalidación"
                        value={
                            conductor.licencia_vence
                                ? formatearFecha(conductor.licencia_vence)
                                : '—'
                        }
                    />
                    {!conductor.activo && (
                        <>
                            <Dato
                                label="Fecha de baja"
                                value={
                                    conductor.fecha_baja
                                        ? formatearFecha(conductor.fecha_baja)
                                        : '—'
                                }
                            />
                            <Dato
                                label="Motivo de baja"
                                value={conductor.motivo_baja ?? '—'}
                            />
                        </>
                    )}
                </dl>
            </section>

            <section>
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold text-foreground">
                        Documentos
                    </h2>

                    {puedeGestionar && (
                        <AgregarDocumentoConductorDialog
                            conductorId={conductor.id}
                            tipos={tiposDocumento}
                        />
                    )}
                </div>

                <div className="flex flex-col gap-2.5">
                    {obligatorias.map((ranura) => (
                        <DocumentoRanuraConductor
                            key={ranura.tipo}
                            ranura={ranura}
                            conductorId={conductor.id}
                            tipos={tiposDocumento}
                            puedeGestionar={puedeGestionar}
                        />
                    ))}

                    {sueltas.length > 0 && (
                        <>
                            <p className="mt-3 text-xs font-medium text-muted-foreground">
                                Otros papeles
                            </p>
                            {sueltas.map((ranura) => (
                                <DocumentoRanuraConductor
                                    key={ranura.documento?.id ?? ranura.tipo}
                                    ranura={ranura}
                                    conductorId={conductor.id}
                                    tipos={tiposDocumento}
                                    puedeGestionar={puedeGestionar}
                                />
                            ))}
                        </>
                    )}
                </div>
            </section>
        </div>
    );
}
