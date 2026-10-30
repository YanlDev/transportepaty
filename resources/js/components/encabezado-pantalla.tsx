import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * La cabecera de cada pantalla, igual en todo el sistema: el ícono del menú,
 * el título y una línea que dice para qué sirve, con los botones principales
 * a la derecha. Quien entra por primera vez sabe en qué pantalla está y qué
 * se hace ahí sin preguntar.
 */
export function EncabezadoPantalla({
    titulo,
    descripcion,
    icono: Icono,
    acciones,
    className,
}: {
    titulo: string;
    descripcion: string;
    /** El mismo del menú lateral. */
    icono: LucideIcon;
    acciones?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icono className="size-5" />
                </span>
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {descripcion}
                    </p>
                </div>
            </div>
            {acciones && (
                <div className="flex flex-wrap items-center gap-2">
                    {acciones}
                </div>
            )}
        </header>
    );
}
