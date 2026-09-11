import { CalendarDays, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Reservado para cuando exista asignación de viajes: hoy la app registra lo
 * que ya pasó (las GR emitidas), no lo que se va a hacer, así que la tarjeta
 * queda como marcador del lugar que ocuparía esa lista. El botón está
 * deshabilitado a propósito —no hay a dónde ir todavía.
 */
export function ConductorProximosViajes() {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    Próximos viajes
                </h2>
                <Button
                    variant="outline"
                    size="sm"
                    disabled
                    title="La asignación de viajes todavía no está implementada"
                >
                    <Plus className="size-4" />
                    Asignar viaje
                </Button>
            </div>

            <div className="flex flex-col items-center gap-2 p-8 text-center">
                <CalendarDays className="size-10 text-muted-foreground/30" />
                <p className="text-sm font-medium">
                    No hay viajes próximos asignados
                </p>
                <p className="text-xs text-muted-foreground">
                    Cuando se asignen viajes, aparecerán aquí.
                </p>
            </div>
        </section>
    );
}
