import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import conductores, {
    asistencia as rutaAsistencia,
    show,
} from '@/actions/App/Http/Controllers/ConductorController';
import { CalendarioAsistenciaAnual } from '@/components/asistencia/calendario-anual';
import { Button } from '@/components/ui/button';
import type { AsistenciaCalendarioAnual } from '@/types/fleet';

type Props = {
    conductor: {
        id: number;
        nombres: string;
        apellidos: string;
        documento: string;
    };
    asistencia: AsistenciaCalendarioAnual;
};

export default function ConductorAsistencia({ conductor, asistencia }: Props) {
    const nombre = `${conductor.apellidos} ${conductor.nombres}`;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Conductores', href: conductores.index().url },
            { title: nombre, href: show(conductor.id).url },
            { title: 'Asistencia', href: rutaAsistencia(conductor.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-4 p-4 md:p-6">
            <Head title={`Asistencia de ${nombre}`} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Asistencia de {nombre}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        DNI {conductor.documento} · año {asistencia.anio}
                    </p>
                </div>

                <Button asChild variant="outline">
                    <Link href={show(conductor.id)}>
                        <ArrowLeft className="size-4" />
                        Volver a la ficha
                    </Link>
                </Button>
            </div>

            <CalendarioAsistenciaAnual
                conductorId={conductor.id}
                anio={asistencia.anio}
                calendarios={asistencia.calendarios}
                urlPagina={rutaAsistencia(conductor.id).url}
            />
        </div>
    );
}
