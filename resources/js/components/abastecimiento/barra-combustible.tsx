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
 * si tiene, la vuelta) que se llena de verde con lo autorizado. Los puntos
 * de carga no se separan: van marcados dentro de la barra, con su nombre
 * debajo, para saber dónde toca cargar.
 */
export function BarraCombustible({ tramos }: { tramos: TramoCombustible[] }) {
    if (tramos.length === 0) {
        return (
            <span className="text-xs text-muted-foreground">
                Sin tarjeta de la pizarra
            </span>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            {tramos.map((tramo) => {
                // Un tanque lleno sin galones configurados pesa como 100 gal
                // en el dibujo: solo para darle un ancho.
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
                const completo = tramo.falta === 0 && tramo.previsto !== null;
                let acumulado = 0;

                return (
                    <div
                        key={tramo.sentido}
                        className="grid grid-cols-[3.25rem_1fr_auto] items-center gap-x-2"
                    >
                        <span
                            className={cn(
                                'text-[11px] font-medium',
                                tramo.sentido === 'ida'
                                    ? 'text-amber-700 dark:text-amber-400'
                                    : 'text-violet-700 dark:text-violet-400',
                            )}
                        >
                            {tramo.sentido === 'ida' ? 'Ida' : 'Vuelta'}
                        </span>
                        <div className="relative h-3 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all',
                                    completo
                                        ? 'bg-emerald-500'
                                        : 'bg-emerald-400',
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
                                ? 'full'
                                : galones(tramo.previsto)}
                        </span>
                        <span />
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
                                        ? 'full'
                                        : Number.isInteger(punto.previsto)
                                          ? punto.previsto
                                          : punto.previsto.toFixed(1)}
                                </span>
                            ))}
                        </div>
                        <span />
                    </div>
                );
            })}
        </div>
    );
}
