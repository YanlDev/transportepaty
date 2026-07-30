import { Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { edit, show } from '@/actions/App/Http/Controllers/ConductorController';
import { DeleteConductorDialog } from '@/components/conductores/delete-conductor-dialog';
import { Copiable } from '@/components/copiable';
import { Button } from '@/components/ui/button';
import type { ConductorListItem } from '@/types/fleet';

type Props = {
    conductor: ConductorListItem;
    puedeGestionar: boolean;
};

/**
 * El conductor como ficha. De las ocho columnas de la tabla se conservan las que
 * se usan en la calle: nombre, celular —para llamar— y licencia con su
 * revalidación. DNI y procedencia quedan en el detalle.
 */
export function ConductorTarjetaMovil({ conductor, puedeGestionar }: Props) {
    return (
        <div className="group/fila flex flex-col gap-2 border bg-card p-3">
            <div className="flex items-start justify-between gap-2">
                <Link
                    href={show(conductor.id)}
                    className="text-sm font-semibold underline-offset-2 hover:underline"
                >
                    {conductor.nombre_completo}
                </Link>

                <span
                    className={
                        conductor.activo
                            ? 'shrink-0 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/20'
                            : 'shrink-0 bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                    }
                >
                    {conductor.activo ? 'Activo' : 'Inactivo'}
                </span>
            </div>

            {conductor.telefono && (
                <div className="flex items-center gap-1">
                    <a
                        href={`tel:${conductor.telefono}`}
                        className="text-sm text-foreground tabular-nums underline-offset-2 hover:underline"
                    >
                        {conductor.telefono}
                    </a>
                    <Copiable valor={conductor.telefono} etiqueta="celular">
                        <span className="sr-only">{conductor.telefono}</span>
                    </Copiable>
                </div>
            )}

            <p className="text-xs text-muted-foreground">
                Licencia{' '}
                <span className="font-mono">{conductor.licencia ?? '—'}</span>
                {conductor.categoria_licencia &&
                    ` · ${conductor.categoria_licencia}`}
                {conductor.licencia_vence &&
                    ` · vence ${conductor.licencia_vence}`}
            </p>

            {puedeGestionar && (
                <div className="flex items-center justify-end gap-1 border-t pt-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950"
                    >
                        <Link
                            href={edit(conductor.id)}
                            aria-label={`Editar ${conductor.nombre_completo}`}
                        >
                            <Pencil className="size-4" />
                        </Link>
                    </Button>
                    <DeleteConductorDialog
                        conductor={conductor}
                        trigger={
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                aria-label={`Eliminar ${conductor.nombre_completo}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        }
                    />
                </div>
            )}
        </div>
    );
}
