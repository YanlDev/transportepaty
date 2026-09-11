import {
    IdentificationCard,
    MapPin,
    PencilSimple,
    Trash,
    Truck,
} from '@phosphor-icons/react';
import { Button } from '@/components/ui/button';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { clienteColor } from '@/lib/cliente-color';
import { cn } from '@/lib/utils';
import type { ProgramacionTarjeta } from '@/types/programacion';

/**
 * Una unidad programada, como tarjeta.
 *
 * El color sale del cliente (`clienteColor`, el mismo hash que usan los
 * chips de los viajes), así que dos unidades del mismo cliente se leen
 * juntas de un vistazo sin tener que comparar nombres largos. La placa va
 * grande porque es el dato con el que abastecimiento identifica la unidad en
 * el patio.
 */
export function TarjetaProgramacion({
    programacion,
    editable,
    onEditar,
    onBorrar,
}: {
    programacion: ProgramacionTarjeta;
    editable: boolean;
    onEditar: () => void;
    onBorrar: () => void;
}) {
    const { punto } = clienteColor(programacion.cliente);

    return (
        <article className="group relative flex flex-col gap-3 overflow-hidden rounded-xl border bg-card p-4 shadow-xs transition hover:shadow-md">
            {/* La franja de color del cliente: es lo que se ve primero al
                barrer la grilla con la vista. */}
            <span
                aria-hidden
                className={cn('absolute inset-y-0 left-0 w-1.5', punto)}
            />

            <div className="flex items-start justify-between gap-2 pl-2">
                <div className="flex items-center gap-2">
                    <Truck className="size-5 shrink-0 text-muted-foreground" />
                    <span className="font-mono text-lg leading-none font-bold tracking-tight">
                        {programacion.placa}
                    </span>
                </div>

                {editable && (
                    <div className="flex shrink-0 gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            onClick={onEditar}
                            aria-label={`Editar la programación de ${programacion.placa}`}
                        >
                            <PencilSimple className="size-4" />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7 text-destructive hover:text-destructive"
                            onClick={onBorrar}
                            aria-label={`Quitar la programación de ${programacion.placa}`}
                        >
                            <Trash className="size-4" />
                        </Button>
                    </div>
                )}
            </div>

            <div className="pl-2">
                <ClienteChip cliente={programacion.cliente} />
            </div>

            <dl className="flex flex-col gap-1.5 pl-2 text-sm">
                <div className="flex items-center gap-2">
                    <dt className="sr-only">Destino</dt>
                    <MapPin className="size-4 shrink-0 text-muted-foreground" />
                    <dd
                        className="truncate font-medium"
                        title={programacion.destino}
                    >
                        {programacion.destino}
                    </dd>
                </div>

                <div className="flex items-center gap-2">
                    <dt className="sr-only">Conductor</dt>
                    <IdentificationCard className="size-4 shrink-0 text-muted-foreground" />
                    <dd
                        className="truncate text-muted-foreground"
                        title={programacion.conductor}
                    >
                        {programacion.conductor}
                    </dd>
                </div>
            </dl>
        </article>
    );
}
