import { Link, router } from '@inertiajs/react';
import { ChevronRight, FileText, Lock, LockOpen, Pencil } from 'lucide-react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { TallerVuelta } from '@/components/taller/taller-vuelta';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { StatusBadge } from '@/components/ui/status-badge';
import { ItinerarioVuelta } from '@/components/vueltas/itinerario-vuelta';
import { ETAPAS } from '@/components/vueltas/tabla-vueltas';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { TarjetaVuelta, Vuelta } from '@/types/vueltas';

/**
 * El itinerario completo de una vuelta, a un costado: ida y regreso con sus
 * GR, el OK de taller y el acceso a editarla.
 */
export function DetalleVuelta({
    vuelta,
    onCerrar,
    onEditar,
}: {
    vuelta: TarjetaVuelta | null;
    onCerrar: () => void;
    onEditar: (vuelta: Vuelta) => void;
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
                                {vuelta.estado === 'en_curso' ? (
                                    <StatusBadge
                                        label={
                                            ETAPAS.find(
                                                (etapa) =>
                                                    etapa.etapa ===
                                                    vuelta.etapa,
                                            )?.titulo ?? vuelta.estado_label
                                        }
                                        tone={
                                            vuelta.etapa === 'en_base'
                                                ? 'success'
                                                : 'warning'
                                        }
                                    />
                                ) : (
                                    <EstadoVueltaBadge vuelta={vuelta} />
                                )}
                                {vuelta.estado === 'cerrada' && (
                                    <Lock
                                        className="size-4 text-muted-foreground"
                                        aria-label="Con candado"
                                    />
                                )}
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
                                {vuelta.cerrable && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    `¿Cerrar el ticket ${vuelta.codigo}? Ya no se le podrá registrar combustible ni viáticos.`,
                                                )
                                            ) {
                                                router.post(
                                                    vueltas.cerrar(vuelta.id)
                                                        .url,
                                                    {},
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        <Lock className="size-3.5" />
                                        Cerrar ticket
                                    </Button>
                                )}
                                {vuelta.reabrible && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                vueltas.reabrir(vuelta.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <LockOpen className="size-3.5" />
                                        Reabrir
                                    </Button>
                                )}
                                <Button size="sm" asChild>
                                    <Link href={vueltas.show(vuelta.id).url}>
                                        <FileText className="size-3.5" />
                                        Ver expediente
                                    </Link>
                                </Button>
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
                                        Vuelta por definir.
                                    </p>
                                )}
                            </div>
                            <TallerVuelta ticket={vuelta} />
                            <TramosVuelta vuelta={vuelta} conGuias />
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
