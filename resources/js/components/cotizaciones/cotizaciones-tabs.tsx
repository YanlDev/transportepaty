import { Link } from '@inertiajs/react';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';

/**
 * Las tres caras del módulo: calcular, lo ya emitido y las tasas. Son un solo
 * trámite —se calcula, se emite, queda en la lista—, así que van como pestañas
 * de una misma entrada del menú y no como módulos sueltos.
 *
 * Solo el admin las ve: el visor entra directo a las emitidas, que es lo único
 * que puede abrir.
 */
export function CotizacionesTabs({
    actual,
}: {
    actual: 'cotizador' | 'emitidas' | 'tarifario';
}) {
    const { puedeEditar } = usePermisos();

    if (!puedeEditar) {
        return null;
    }

    const pestanas = [
        {
            clave: 'cotizador',
            titulo: 'Cotizador',
            href: cotizaciones.cotizador(),
        },
        { clave: 'emitidas', titulo: 'Emitidas', href: cotizaciones.index() },
        {
            clave: 'tarifario',
            titulo: 'Tarifario',
            href: parametrosCosto.edit(),
        },
    ] as const;

    return (
        <nav
            aria-label="Secciones de cotizaciones"
            className="flex gap-1 border-b border-border"
        >
            {pestanas.map((pestana) => (
                <Link
                    key={pestana.clave}
                    href={pestana.href}
                    aria-current={pestana.clave === actual ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                        pestana.clave === actual
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {pestana.titulo}
                </Link>
            ))}
        </nav>
    );
}
