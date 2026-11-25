import { formatearFechaHora } from '@/lib/format';
import type { PasoDeOrden } from '@/types/mantenimiento';

/**
 * Los pasos que dio la orden, cada uno con el usuario que lo dio y la hora:
 * quién la registró, quién la empezó, quién la terminó y quién de taller la
 * finalizó. Si alguien anotó el paso por el mecánico, también se dice.
 */
export function LineaDeTiempo({ pasos }: { pasos: PasoDeOrden[] }) {
    return (
        <ol className="relative ml-2 grid gap-4 border-l pl-5">
            {pasos.map((paso, indice) => (
                <li key={indice} className="relative text-sm">
                    <span className="absolute top-1.5 -left-[25px] size-2.5 rounded-full bg-primary ring-4 ring-card" />
                    <p className="font-medium">{paso.paso}</p>
                    <p className="text-xs text-muted-foreground">
                        {paso.usuario ?? 'Sin usuario'}
                        {paso.registrado_por &&
                            ` (registrado por ${paso.registrado_por})`}
                        {paso.cuando && ` · ${formatearFechaHora(paso.cuando)}`}
                    </p>
                    {paso.nota && (
                        <p className="mt-1 rounded-md bg-muted/60 p-2 text-xs whitespace-pre-line">
                            {paso.nota}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
