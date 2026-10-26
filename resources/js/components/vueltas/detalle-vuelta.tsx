import { Link } from '@inertiajs/react';
import { ChevronRight, Pencil } from 'lucide-react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { AvisosVuelta } from '@/components/vueltas/avisos-vuelta';
import { ItinerarioVuelta } from '@/components/vueltas/itinerario-vuelta';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { TarjetaVuelta, Vuelta } from '@/types/vueltas';

/**
 * El itinerario completo de una vuelta, a un costado: ida y regreso con sus
 * GR, los avisos de WhatsApp y el acceso a editarla.
 */
export function DetalleVuelta({
    vuelta,
    onCerrar,
    onEditar,
    conectado,
    puedeAvisar,
}: {
    vuelta: TarjetaVuelta | null;
    onCerrar: () => void;
    onEditar: (vuelta: Vuelta) => void;
    conectado: boolean;
    puedeAvisar: boolean;
}) {
    return (
        <Sheet
            open={vuelta !== null}
            onOpenChange={(abierto) => !abierto && onCerrar()}
        >
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-xl">
                {vuelta && (
                    <>
                        <SheetHeader className="border-b">
                            <SheetTitle className="flex items-center gap-2">
                                <span className="font-mono">
                                    {formatearPlaca(vuelta.placa)}
                                </span>
                                <span className="font-mono text-sm text-muted-foreground">
                                    {vuelta.codigo}
                                </span>
                                <EstadoVueltaBadge vuelta={vuelta} />
                            </SheetTitle>
                            <SheetDescription>
                                {vuelta.tipo_label}
                                {vuelta.inicio &&
                                    ` · desde ${formatearFecha(vuelta.inicio)}`}
                                {vuelta.fin &&
                                    ` hasta ${formatearFecha(vuelta.fin)}`}
                                {' · '}
                                {vuelta.conductor ?? 'Sin conductor'}
                                {vuelta.carreta &&
                                    ` · carreta ${formatearPlaca(vuelta.carreta)}`}
                            </SheetDescription>
                            <div className="flex gap-2 pt-1">
                                {vuelta.editable && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onEditar(vuelta)}
                                    >
                                        <Pencil className="size-3.5" />
                                        Editar
                                    </Button>
                                )}
                                <Button variant="ghost" size="sm" asChild>
                                    <Link
                                        href={
                                            vueltas.unidad(vuelta.tracto_id).url
                                        }
                                    >
                                        Historia de la unidad
                                        <ChevronRight className="size-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </SheetHeader>

                        <div className="flex flex-col gap-5 p-4">
                            <div className="rounded-xl bg-muted/40 p-4">
                                <ItinerarioVuelta
                                    paradas={[
                                        ...vuelta.ida,
                                        ...vuelta.regreso,
                                    ].map((parada) => ({
                                        sentido: parada.sentido,
                                        desde: parada.origen ?? '¿?',
                                        hasta: parada.destino ?? '¿?',
                                        carga:
                                            parada.tipo_carga === 'vacio'
                                                ? 'vacía'
                                                : parada.tipo_carga_label,
                                        vacio: parada.tipo_carga === 'vacio',
                                        hecha: parada.guias.length > 0,
                                    }))}
                                />
                                {vuelta.regreso.length === 0 && (
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        Regreso por definir.
                                    </p>
                                )}
                            </div>
                            <TramosVuelta vuelta={vuelta} conGuias />
                            <AvisosVuelta
                                salida={vuelta}
                                conectado={conectado}
                                puedeAvisar={puedeAvisar}
                            />
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
