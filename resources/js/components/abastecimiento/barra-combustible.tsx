import { CircleCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { TramoCombustible } from '@/types/abastecimiento';

/** Galones en corto: «220 gal», «12.5 gal», o «—» si no se sabe. */
export const galones = (valor: number | null): string =>
    valor === null
        ? '—'
        : `${Number.isInteger(valor) ? valor : valor.toFixed(1)} gal`;

/** Soles con dos decimales: «S/ 2,600.00». */
export const soles = (valor: number | null): string =>
    valor === null
        ? '—'
        : `S/ ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * El combustible de un ticket de un vistazo: una barra por tramo (la ida y,
 * si tiene, la vuelta) que se llena de verde con lo autorizado contra el
 * tope de sus rutas. Los puntos de carga no se separan: van marcados dentro
 * de la barra, con su nombre debajo. Un tramo sin tope (falta la variante o
 * hay galones por definir) no tiene barra: dice qué falta.
 */
export function BarraCombustible({
    tramos: todos,
    sentido,
}: {
    tramos: TramoCombustible[];
    /** Solo ese tramo, sin su etiqueta: la pantalla ya dice cuál es. */
    sentido?: 'ida' | 'regreso';
}) {
    const tramos =
        sentido === undefined
            ? todos
            : todos.filter((tramo) => tramo.sentido === sentido);
    const conEtiqueta = sentido === undefined;

    if (tramos.length === 0) {
        return (
            <span className="text-xs text-muted-foreground">
                {sentido === 'regreso'
                    ? 'Vuelta por definir'
                    : 'Sin tramos programados'}
            </span>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            {tramos.map((tramo) => {
                if (!tramo.confirmada) {
                    return (
                        <div
                            key={tramo.sentido}
                            className={cn(
                                'grid items-center gap-x-2',
                                conEtiqueta && 'grid-cols-[3.25rem_1fr]',
                            )}
                        >
                            {conEtiqueta && (
                                <span className="text-[11px] font-medium text-muted-foreground">
                                    {tramo.sentido === 'ida' ? 'Ida' : 'Vuelta'}
                                </span>
                            )}
                            <span
                                className="truncate text-[11px] text-muted-foreground"
                                title={tramo.pendiente ?? undefined}
                            >
                                {tramo.pendiente ?? 'Sin tope'}
                            </span>
                        </div>
                    );
                }

                // Una línea con galones por definir pesa como 100 gal en el
                // dibujo: solo para darle un ancho.
                const pesos = tramo.puntos.map(
                    (punto) => punto.previsto ?? 100,
                );
                const total = pesos.reduce((suma, peso) => suma + peso, 0) || 1;
                const avance =
                    tramo.previsto && tramo.previsto > 0
                        ? Math.min(
                              100,
                              (tramo.registrado / tramo.previsto) * 100,
                          )
                        : tramo.registrado > 0
                          ? 100
                          : 0;
                const conFull = tramo.tanque_lleno.puntos.length > 0;
                const completo =
                    tramo.falta === 0 &&
                    tramo.previsto !== null &&
                    (!conFull || tramo.tanque_lleno.usado);
                let acumulado = 0;

                return (
                    <div
                        key={tramo.sentido}
                        className={cn(
                            'grid items-center gap-x-2',
                            conEtiqueta
                                ? 'grid-cols-[3.25rem_1fr_auto]'
                                : 'grid-cols-[1fr_auto]',
                        )}
                    >
                        {conEtiqueta && (
                            <span className="text-[11px] font-medium text-muted-foreground">
                                {tramo.sentido === 'ida' ? 'Ida' : 'Vuelta'}
                            </span>
                        )}
                        <div className="relative h-3 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all',
                                    completo ? 'bg-emerald-500' : 'bg-primary',
                                )}
                                style={{ width: `${avance}%` }}
                            />
                            {/* Dónde termina cada punto y empieza el siguiente. */}
                            {pesos.slice(0, -1).map((peso, indice) => {
                                acumulado += peso;

                                return (
                                    <span
                                        key={indice}
                                        className="absolute inset-y-0 w-0.5 bg-background"
                                        style={{
                                            left: `${(acumulado / total) * 100}%`,
                                        }}
                                    />
                                );
                            })}
                        </div>
                        <span
                            className={cn(
                                'flex items-center gap-1 text-[11px] tabular-nums',
                                completo
                                    ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {completo && <CircleCheck className="size-3" />}
                            {galones(tramo.registrado).replace(' gal', '')}/
                            {tramo.previsto === null
                                ? '¿? gal'
                                : galones(tramo.previsto)}
                        </span>
                        {conEtiqueta && <span />}
                        <div
                            className="flex text-[10px] text-muted-foreground"
                            title="Sugerido: el reparto entre puntos puede variar"
                        >
                            {tramo.puntos.map((punto, indice) => (
                                <span
                                    key={punto.punto}
                                    className="truncate"
                                    style={{
                                        flexGrow: pesos[indice],
                                        flexBasis: 0,
                                    }}
                                >
                                    {punto.punto}{' '}
                                    {punto.previsto === null
                                        ? '¿?'
                                        : Number.isInteger(punto.previsto)
                                          ? punto.previsto
                                          : punto.previsto.toFixed(1)}
                                </span>
                            ))}
                        </div>
                        <span />
                        {conFull && (
                            <>
                                {conEtiqueta && <span />}
                                <span
                                    className="text-[10px] text-muted-foreground"
                                    title="Los galones los dice el voucher y descuentan del tope del tramo"
                                >
                                    + tanque full en{' '}
                                    {tramo.tanque_lleno.puntos
                                        .map((punto) => punto.punto)
                                        .join(' o ')}
                                    {tramo.tanque_lleno.usado
                                        ? tramo.tanque_lleno.galones > 0
                                            ? ` · ${galones(tramo.tanque_lleno.galones)}`
                                            : ' · falta el voucher: no se autoriza más hasta registrarlo'
                                        : ' · pendiente'}
                                </span>
                                <span />
                            </>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
