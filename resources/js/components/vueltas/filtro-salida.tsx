import { X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** El rango de días que se quiere ver (`AAAA-MM-DD` o vacío). */
export type RangoSalida = { desde: string; hasta: string };

/** Qué fecha del ticket se busca: su salida o la de sus despachos. */
export type CriterioFecha = 'salida' | 'despacho';

export const SIN_RANGO: RangoSalida = { desde: '', hasta: '' };

const CRITERIOS: { criterio: CriterioFecha; titulo: string }[] = [
    { criterio: 'salida', titulo: 'Salida' },
    { criterio: 'despacho', titulo: 'Despacho' },
];

/** El día local como `AAAA-MM-DD`, corrido tantos días. */
function dia(corrido = 0): string {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + corrido);

    return [
        fecha.getFullYear(),
        String(fecha.getMonth() + 1).padStart(2, '0'),
        String(fecha.getDate()).padStart(2, '0'),
    ].join('-');
}

/** Los atajos: hoy, ayer y la semana en curso (de lunes a hoy). */
function atajos(): { titulo: string; rango: RangoSalida }[] {
    const desdeElLunes = (new Date().getDay() + 6) % 7;

    return [
        { titulo: 'Hoy', rango: { desde: dia(), hasta: dia() } },
        { titulo: 'Ayer', rango: { desde: dia(-1), hasta: dia(-1) } },
        {
            titulo: 'Esta semana',
            rango: { desde: dia(-desdeElLunes), hasta: dia() },
        },
    ];
}

/** Si el rango no deja pasar nada: no hay ningún extremo puesto. */
export function sinRango(rango: RangoSalida): boolean {
    return rango.desde === '' && rango.hasta === '';
}

/**
 * Si esa fecha cae dentro del rango. Con un solo extremo puesto el otro
 * queda abierto; sin fecha solo pasa cuando no hay rango.
 */
export function saleEnRango(
    fecha: string | null | undefined,
    rango: RangoSalida,
): boolean {
    if (sinRango(rango)) {
        return true;
    }

    if (!fecha) {
        return false;
    }

    const elDia = fecha.slice(0, 10);

    return (
        (rango.desde === '' || elDia >= rango.desde) &&
        (rango.hasta === '' || elDia <= rango.hasta)
    );
}

/**
 * Buscar los tickets por fecha: un día solo (el mismo en las dos casillas),
 * un rango o un atajo. Con `onCriterio` deja elegir si la fecha es la de la
 * salida del ticket o la de sus despachos de combustible.
 */
export function FiltroSalida({
    rango,
    onCambiar,
    criterio = 'salida',
    onCriterio,
}: {
    rango: RangoSalida;
    onCambiar: (rango: RangoSalida) => void;
    criterio?: CriterioFecha;
    onCriterio?: (criterio: CriterioFecha) => void;
}) {
    const etiqueta = criterio === 'salida' ? 'Salida' : 'Despacho';

    return (
        <div className="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
            {onCriterio ? (
                <div className="inline-flex rounded-lg border p-0.5">
                    {CRITERIOS.map((opcion) => (
                        <button
                            key={opcion.criterio}
                            type="button"
                            onClick={() => onCriterio(opcion.criterio)}
                            aria-pressed={criterio === opcion.criterio}
                            className={cn(
                                'min-h-8 rounded-md px-2.5 text-sm transition pointer-coarse:min-h-10',
                                criterio === opcion.criterio
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'hover:text-foreground',
                            )}
                        >
                            {opcion.titulo}
                        </button>
                    ))}
                </div>
            ) : (
                <span className="whitespace-nowrap">Salida</span>
            )}
            <span>del</span>
            <Input
                type="date"
                value={rango.desde}
                max={rango.hasta || undefined}
                onChange={(evento) =>
                    onCambiar({ ...rango, desde: evento.target.value })
                }
                aria-label={`${etiqueta} desde`}
                className="w-36"
            />
            <span>al</span>
            <Input
                type="date"
                value={rango.hasta}
                min={rango.desde || undefined}
                onChange={(evento) =>
                    onCambiar({ ...rango, hasta: evento.target.value })
                }
                aria-label={`${etiqueta} hasta`}
                className="w-36"
            />
            {atajos().map((atajo) => {
                const activo =
                    rango.desde === atajo.rango.desde &&
                    rango.hasta === atajo.rango.hasta;

                return (
                    <button
                        key={atajo.titulo}
                        type="button"
                        onClick={() =>
                            onCambiar(activo ? SIN_RANGO : atajo.rango)
                        }
                        aria-pressed={activo}
                        className={cn(
                            'min-h-8 rounded-full border px-2.5 text-xs whitespace-nowrap transition pointer-coarse:min-h-10',
                            activo
                                ? 'border-primary bg-accent font-medium text-accent-foreground'
                                : 'hover:text-foreground',
                        )}
                    >
                        {atajo.titulo}
                    </button>
                );
            })}
            {!sinRango(rango) && (
                <button
                    type="button"
                    onClick={() => onCambiar(SIN_RANGO)}
                    aria-label="Quitar el filtro de fechas"
                    className="rounded-md p-1.5 hover:bg-muted hover:text-foreground"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
