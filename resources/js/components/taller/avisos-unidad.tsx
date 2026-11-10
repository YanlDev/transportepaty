import { Link } from '@inertiajs/react';
import { CircleAlert, CircleDot, Gauge, Wrench } from 'lucide-react';
import { cn } from '@/lib/utils';

export type AvisoTaller = {
    tipo: 'orden' | 'preventivo' | 'llantas';
    tono: 'mal' | 'aviso';
    texto: string;
    url: string;
};

const ICONO = {
    orden: Wrench,
    preventivo: Gauge,
    llantas: CircleDot,
} as const;

/**
 * Lo que hay que saber de la unidad antes de darle el apto: orden de trabajo
 * abierta, preventivo vencido o por vencer, llantas por cambiar. Cada aviso
 * lleva a donde se resuelve; quien no puede ver el mantenimiento no los
 * recibe del servidor.
 *
 * Sin avisos no se dibuja nada: una unidad sin pendientes no necesita que se
 * lo digan en cada tarjeta.
 */
export function AvisosUnidad({
    avisos,
    className,
}: {
    avisos: AvisoTaller[];
    className?: string;
}) {
    if (avisos.length === 0) {
        return null;
    }

    return (
        <ul className={cn('flex flex-wrap gap-1.5', className)}>
            {avisos.map((aviso) => {
                const Icono = ICONO[aviso.tipo] ?? CircleAlert;

                return (
                    <li key={`${aviso.tipo}-${aviso.texto}`}>
                        <Link
                            href={aviso.url}
                            className={cn(
                                'inline-flex min-h-7 items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors pointer-coarse:min-h-9',
                                aviso.tono === 'mal'
                                    ? 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100 dark:border-red-900 dark:bg-red-950 dark:text-red-200 dark:hover:bg-red-900'
                                    : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200 dark:hover:bg-amber-900',
                            )}
                        >
                            <Icono className="size-3.5 shrink-0" />
                            {aviso.texto}
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
