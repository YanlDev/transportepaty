import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ChevronRight,
    CircleDot,
    ClipboardCheck,
    ClipboardPlus,
    Clock,
    Gauge,
    Wrench,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import llantas from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { create as crearOrden } from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import {
    colorEstado,
    TarjetaMarca,
} from '@/components/mantenimiento/marca-tarjeta';
import type { ConteoEstados } from '@/components/mantenimiento/marca-tarjeta';
import { RegistrarKmDialog } from '@/components/mantenimiento/registrar-km-dialog';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';
import type {
    MarcaMantenimiento,
    ResumenMantenimiento,
    TractoKm,
} from '@/types/mantenimiento';

type Props = {
    /** El semáforo de toda la flota con plan. */
    resumen: ResumenMantenimiento;
    marcas: (MarcaMantenimiento & { conteo: ConteoEstados })[];
    /** Los tractos con plan. */
    unidades: number;
    /** Cuántos tienen el último km viejo. */
    desactualizados: number;
    tractos: TractoKm[];
};

/**
 * La portada de mantenimiento: cómo está la flota de un vistazo y los
 * accesos a cada parte del módulo. El detalle unidad por unidad está en
 * Preventivo; desde acá se llega ya filtrado por estado o por marca.
 */
export default function MantenimientoIndex({
    resumen,
    marcas,
    unidades,
    desactualizados,
    tractos,
}: Props) {
    const { puede } = usePermisos();
    const puedeKm = puede('mantenimiento.registrar_km');
    const puedeOrdenes = puede('mantenimiento.gestionar');
    const [kmAbierto, setKmAbierto] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Mantenimiento" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {unidades} {unidades === 1 ? 'tracto' : 'tractos'} con plan
                    preventivo · {marcas.length}{' '}
                    {marcas.length === 1 ? 'marca' : 'marcas'}
                </p>

                <div className="flex gap-2 max-md:hidden">
                    {puedeOrdenes && (
                        <Button variant="outline" asChild>
                            <Link href={crearOrden()}>
                                <ClipboardPlus className="size-4" />
                                Nueva orden
                            </Link>
                        </Button>
                    )}
                    {puedeKm && <RegistrarKmDialog tractos={tractos} />}
                </div>
            </div>

            {/* Cada número lleva al preventivo ya filtrado por ese estado. */}
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {resumen.map((item) => (
                    <Link
                        key={item.estado}
                        href={mantenimiento.preventivo({
                            query: { estado: item.estado },
                        })}
                        className="flex flex-col items-start rounded-xl border bg-card p-3 transition-colors hover:bg-accent/50"
                    >
                        <span
                            className={cn(
                                'text-2xl font-semibold tabular-nums',
                                colorEstado[item.estado],
                            )}
                        >
                            {item.cantidad}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {item.label}
                        </span>
                    </Link>
                ))}
            </div>

            {desactualizados > 0 && (
                <Link
                    href={mantenimiento.preventivo({
                        query: { desactualizados: 1 },
                    })}
                    className="flex items-center gap-2 self-start rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950/70"
                >
                    <Clock className="size-4" />
                    {desactualizados}{' '}
                    {desactualizados === 1
                        ? 'unidad con el km desactualizado'
                        : 'unidades con el km desactualizado'}
                    <ChevronRight className="size-4" />
                </Link>
            )}

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-medium text-muted-foreground">
                    Accesos rápidos
                </h2>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 xl:grid-cols-5">
                    <AccesoRapido
                        icono={<Wrench />}
                        titulo="Preventivo"
                        descripcion="Qué servicio le toca a cada tracto"
                        href={mantenimiento.preventivo().url}
                    />
                    {puedeKm && (
                        <AccesoRapido
                            icono={<Gauge />}
                            titulo="Registrar km"
                            descripcion="Al llegar la unidad a base"
                            onClick={() => setKmAbierto(true)}
                        />
                    )}
                    {puedeOrdenes && (
                        <AccesoRapido
                            icono={<ClipboardPlus />}
                            titulo="Nueva orden"
                            descripcion="Preventiva o correctiva, con fotos"
                            href={crearOrden().url}
                        />
                    )}
                    <AccesoRapido
                        icono={<CircleDot />}
                        titulo="Llantas"
                        descripcion="Tractos 10 · carretas 12 + 2 de repuesto"
                        href={llantas.index().url}
                    />
                    <AccesoRapido
                        icono={<ClipboardCheck />}
                        titulo="Checklist del conductor"
                        descripcion="Fallas con fotos que abren una orden"
                        proximamente
                    />
                </div>
            </section>

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-medium text-muted-foreground">
                    Por marca
                </h2>
                <div className="grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-4">
                    {marcas.map((marca) => (
                        <TarjetaMarca
                            key={marca.id}
                            marca={marca}
                            conteo={marca.conteo}
                            href={
                                mantenimiento.preventivo({
                                    query: { marca: marca.marca },
                                }).url
                            }
                        />
                    ))}
                </div>
            </section>

            {puedeKm && (
                <>
                    <RegistrarKmDialog
                        tractos={tractos}
                        abierto={kmAbierto}
                        onAbiertoChange={setKmAbierto}
                    />
                    <AccionPrincipalMovil
                        onClick={() => setKmAbierto(true)}
                        icono={<Gauge />}
                    >
                        Registrar km
                    </AccionPrincipalMovil>
                </>
            )}
        </div>
    );
}

/**
 * Una entrada del módulo. Las que todavía no existen se muestran igual, sin
 * enlace, para que se vea hacia dónde va el módulo.
 */
function AccesoRapido({
    icono,
    titulo,
    descripcion,
    href,
    onClick,
    proximamente = false,
}: {
    icono: ReactNode;
    titulo: string;
    descripcion: string;
    href?: string;
    onClick?: () => void;
    proximamente?: boolean;
}) {
    const contenido = (
        <>
            <span
                className={cn(
                    'flex size-10 items-center justify-center rounded-lg [&_svg]:size-5',
                    proximamente
                        ? 'bg-muted text-muted-foreground'
                        : 'bg-primary/10 text-primary',
                )}
            >
                {icono}
            </span>
            <span className="flex flex-col gap-0.5">
                <span className="flex flex-wrap items-center gap-x-2 font-medium">
                    {titulo}
                    {proximamente && (
                        <span className="rounded-full bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground">
                            Próximamente
                        </span>
                    )}
                </span>
                <span className="text-xs text-muted-foreground">
                    {descripcion}
                </span>
            </span>
        </>
    );

    const clases =
        'flex flex-col items-start gap-3 rounded-xl border bg-card p-3 text-left sm:p-4';

    if (proximamente) {
        return (
            <div className={cn(clases, 'border-dashed opacity-75')}>
                {contenido}
            </div>
        );
    }

    if (href !== undefined) {
        return (
            <Link
                href={href}
                className={cn(clases, 'transition-colors hover:bg-accent/50')}
            >
                {contenido}
            </Link>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(clases, 'transition-colors hover:bg-accent/50')}
        >
            {contenido}
        </button>
    );
}
