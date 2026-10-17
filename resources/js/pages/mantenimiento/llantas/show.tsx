import { Head, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Pencil,
    Plus,
    Ruler,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import llantas, {
    eliminarMovimiento,
} from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { ConfirmarBorradoDialog } from '@/components/confirmar-borrado-dialog';
import { CorregirMovimientoDialog } from '@/components/llantas/corregir-movimiento-dialog';
import { EditarLlantaDialog } from '@/components/llantas/editar-llanta-dialog';
import {
    colorLlanta,
    EsquemaLlantas,
    formatearMm,
    LeyendaLlantas,
} from '@/components/llantas/esquema-llantas';
import { MedirLlantasDialog } from '@/components/llantas/medir-llantas-dialog';
import { MovimientoLlantaDialog } from '@/components/llantas/movimiento-llanta-dialog';
import type { TipoMovimiento } from '@/components/llantas/movimiento-llanta-dialog';
import { formatearKm } from '@/components/mantenimiento/estado-mantenimiento';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type {
    MovimientoLlanta,
    PosicionLlanta,
    UmbralesLlanta,
} from '@/types/llantas';

type Props = {
    unidad: {
        id: number;
        placa: string;
        marca: string | null;
        modelo: string | null;
        tipo: 'tracto' | 'carreta';
        /** Null en las carretas. */
        km_actual: number | null;
    };
    posiciones: PosicionLlanta[];
    movimientos: MovimientoLlanta[];
    hoy: string;
    condiciones: EnumOption[];
    motivos: EnumOption[];
    umbrales: UmbralesLlanta;
};

/**
 * Las llantas de una unidad: el esquema con el color de cada posición, lo
 * que hay en la elegida con sus acciones, y el historial. En el celular se
 * apila en ese mismo orden.
 */
export default function LlantasUnidad({
    unidad,
    posiciones,
    movimientos,
    hoy,
    condiciones,
    motivos,
    umbrales,
}: Props) {
    const { puede } = usePermisos();
    const puedeRegistrar = puede('mantenimiento.llantas');
    const esTracto = unidad.tipo === 'tracto';
    const placa = formatearPlaca(unidad.placa);

    const [elegida, setElegida] = useState<string | null>(
        () => posiciones.find((p) => p.estado === 'cambiar')?.codigo ?? null,
    );
    const [accion, setAccion] = useState<TipoMovimiento | 'editar' | null>(
        null,
    );
    const [corrigiendo, setCorrigiendo] = useState<MovimientoLlanta | null>(
        null,
    );
    const [midiendo, setMidiendo] = useState(false);
    const [soloElegida, setSoloElegida] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Llantas', href: llantas.index().url },
            { title: placa, href: llantas.show(unidad.id).url },
        ],
    });

    const posicion = posiciones.find((p) => p.codigo === elegida) ?? null;
    const montadas = posiciones.filter((p) => p.llanta !== null).length;

    const historial =
        soloElegida && posicion
            ? movimientos.filter(
                  (m) =>
                      m.posicion === posicion.codigo ||
                      m.posicion_destino === posicion.codigo,
              )
            : movimientos;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Llantas ${placa}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-0.5">
                    <h1 className="text-xl font-semibold tracking-wide">
                        {placa}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {esTracto ? 'Tracto' : 'Carreta'}
                        {unidad.marca && ` · ${unidad.marca}`}
                        {' · '}
                        {montadas} de {posiciones.length} llantas
                        {esTracto &&
                            unidad.km_actual !== null &&
                            ` · ${formatearKm(unidad.km_actual)}`}
                    </p>
                </div>
                {puedeRegistrar && montadas > 0 && (
                    <Button onClick={() => setMidiendo(true)}>
                        <Ruler className="size-4" />
                        Medir llantas
                    </Button>
                )}
            </div>

            <div className="grid gap-6 md:grid-cols-[auto_minmax(0,1fr)]">
                <div className="flex flex-col gap-4 rounded-xl border bg-card p-4">
                    <EsquemaLlantas
                        posiciones={posiciones}
                        seleccionada={elegida}
                        onElegir={(codigo) =>
                            setElegida(codigo === elegida ? null : codigo)
                        }
                    />
                    <LeyendaLlantas umbrales={umbrales} />
                </div>

                <div className="flex flex-col gap-6">
                    <DetallePosicion
                        posicion={posicion}
                        esTracto={esTracto}
                        kmActual={unidad.km_actual}
                        puedeRegistrar={puedeRegistrar}
                        onAccion={setAccion}
                    />

                    <section className="flex flex-col gap-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-medium">Historial</h2>
                            {posicion && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSoloElegida(!soloElegida)}
                                    aria-pressed={soloElegida}
                                >
                                    {soloElegida
                                        ? 'Ver todas las posiciones'
                                        : `Solo la posición ${posicion.codigo}`}
                                </Button>
                            )}
                        </div>
                        {historial.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                                Todavía no hay movimientos registrados.
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y rounded-xl border bg-card">
                                {historial.map((movimiento) => (
                                    <FilaMovimiento
                                        key={movimiento.id}
                                        movimiento={movimiento}
                                        puedeCorregir={puedeRegistrar}
                                        onCorregir={() =>
                                            setCorrigiendo(movimiento)
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>

            {posicion?.llanta && accion === 'editar' && (
                <EditarLlantaDialog
                    key={`editar-${posicion.llanta.id}`}
                    posicion={{ ...posicion, llanta: posicion.llanta }}
                    condiciones={condiciones}
                    abierto
                    onAbiertoChange={(abierto) => !abierto && setAccion(null)}
                />
            )}

            {corrigiendo && (
                <CorregirMovimientoDialog
                    key={corrigiendo.id}
                    movimiento={corrigiendo}
                    esTracto={esTracto}
                    hoy={hoy}
                    abierto
                    onAbiertoChange={(abierto) =>
                        !abierto && setCorrigiendo(null)
                    }
                />
            )}

            {posicion && accion && accion !== 'editar' && (
                <MovimientoLlantaDialog
                    key={`${accion}-${posicion.codigo}`}
                    vehiculoId={unidad.id}
                    esTracto={esTracto}
                    kmActual={unidad.km_actual}
                    hoy={hoy}
                    tipo={accion}
                    posicion={posicion}
                    posiciones={posiciones}
                    condiciones={condiciones}
                    motivos={motivos}
                    abierto
                    onAbiertoChange={(abierto) => !abierto && setAccion(null)}
                />
            )}

            {midiendo && (
                <MedirLlantasDialog
                    vehiculoId={unidad.id}
                    esTracto={esTracto}
                    kmActual={unidad.km_actual}
                    hoy={hoy}
                    posiciones={posiciones}
                    abierto
                    onAbiertoChange={setMidiendo}
                />
            )}
        </div>
    );
}

/** Lo que hay en la posición elegida y lo que se le puede hacer. */
function DetallePosicion({
    posicion,
    esTracto,
    kmActual,
    puedeRegistrar,
    onAccion,
}: {
    posicion: PosicionLlanta | null;
    esTracto: boolean;
    kmActual: number | null;
    puedeRegistrar: boolean;
    onAccion: (accion: TipoMovimiento | 'editar') => void;
}) {
    if (posicion === null) {
        return (
            <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                Toca una llanta del esquema para ver su detalle.
            </p>
        );
    }

    const llanta = posicion.llanta;

    return (
        <section className="flex flex-col gap-4 rounded-xl border bg-card p-4">
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center rounded-lg border-2 text-lg font-bold',
                        posicion.estado === null
                            ? 'border-dashed border-muted-foreground/40 text-muted-foreground'
                            : colorLlanta[posicion.estado],
                    )}
                >
                    {posicion.codigo}
                </span>
                <div className="flex flex-col">
                    <span className="font-medium">{posicion.nombre}</span>
                    <span className="text-sm text-muted-foreground">
                        {posicion.estado_label}
                        {llanta?.profundidad_mm != null &&
                            ` · ${formatearMm(llanta.profundidad_mm)} el ${formatearFecha(llanta.medida_el)}`}
                    </span>
                </div>
            </div>

            {llanta ? (
                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                    <Dato
                        etiqueta="Llanta"
                        valor={
                            [llanta.marca, llanta.medida]
                                .filter(Boolean)
                                .join(' ') || '—'
                        }
                    />
                    <Dato etiqueta="Condición" valor={llanta.condicion_label} />
                    {llanta.codigo && (
                        <Dato etiqueta="Código" valor={llanta.codigo} />
                    )}
                    <Dato
                        etiqueta="Instalada"
                        valor={formatearFecha(llanta.instalada_el)}
                    />
                    {esTracto && llanta.km_instalacion !== null && (
                        <Dato
                            etiqueta="Recorrido"
                            valor={
                                kmActual === null
                                    ? '—'
                                    : formatearKm(
                                          Math.max(
                                              0,
                                              kmActual - llanta.km_instalacion,
                                          ),
                                      )
                            }
                        />
                    )}
                </dl>
            ) : (
                <p className="text-sm text-muted-foreground">Posición vacía.</p>
            )}

            {puedeRegistrar && (
                <div className="flex flex-wrap gap-2">
                    {llanta === null ? (
                        <Button onClick={() => onAccion('instalacion')}>
                            <Plus className="size-4" />
                            Instalar llanta
                        </Button>
                    ) : (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => onAccion('rotacion')}
                            >
                                <ArrowLeftRight className="size-4" />
                                Rotar
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => onAccion('reparacion')}
                            >
                                <Wrench className="size-4" />
                                Reparación
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => onAccion('retiro')}
                            >
                                <Trash2 className="size-4" />
                                Retirar
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() => onAccion('editar')}
                            >
                                <Pencil className="size-4" />
                                Editar datos
                            </Button>
                        </>
                    )}
                </div>
            )}
        </section>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div className="flex flex-col">
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium">{valor}</dd>
        </div>
    );
}

function FilaMovimiento({
    movimiento,
    puedeCorregir,
    onCorregir,
}: {
    movimiento: MovimientoLlanta;
    puedeCorregir: boolean;
    onCorregir: () => void;
}) {
    return (
        <li className="flex items-start gap-2 px-4 py-3 text-sm">
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <div className="flex items-baseline justify-between gap-2">
                    <span className="font-medium">
                        {movimiento.tipo_label} · posición {movimiento.posicion}
                        {movimiento.posicion_destino &&
                            ` → ${movimiento.posicion_destino}`}
                    </span>
                    <span className="shrink-0 text-xs text-muted-foreground">
                        {formatearFecha(movimiento.fecha)}
                    </span>
                </div>
                <span className="text-xs text-muted-foreground">
                    {[
                        movimiento.llanta,
                        movimiento.profundidad_mm !== null &&
                            formatearMm(movimiento.profundidad_mm),
                        movimiento.kilometraje !== null &&
                            formatearKm(movimiento.kilometraje),
                        movimiento.usuario,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
                {movimiento.observacion && (
                    <span className="text-xs">{movimiento.observacion}</span>
                )}
            </div>
            {puedeCorregir && (
                <div className="-my-1 flex shrink-0">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={onCorregir}
                        aria-label={`Corregir ${movimiento.tipo_label.toLowerCase()}`}
                    >
                        <Pencil className="size-3.5" />
                    </Button>
                    {movimiento.eliminable && (
                        <ConfirmarBorradoDialog
                            url={eliminarMovimiento(movimiento.id).url}
                            titulo={`¿Eliminar esta ${movimiento.tipo_label.toLowerCase()}?`}
                            descripcion={
                                movimiento.tipo === 'instalacion'
                                    ? 'Se borra la llanta y la posición queda libre. Úsalo solo si se cargó por error.'
                                    : movimiento.tipo === 'retiro'
                                      ? 'La llanta vuelve a quedar puesta en su posición.'
                                      : 'Se borra del historial. El semáforo vuelve a la medida anterior.'
                            }
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label={`Eliminar ${movimiento.tipo_label.toLowerCase()}`}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            }
                        />
                    )}
                </div>
            )}
        </li>
    );
}
