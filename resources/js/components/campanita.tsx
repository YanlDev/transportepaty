import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    abrir,
    index as bandeja,
    leerTodas,
    sondearCampanita,
} from '@/actions/App/Http/Controllers/AlertaController';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { haceCuanto } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Alerta, Campanita as DatosCampanita } from '@/types/alertas';

/** Cada cuánto se vuelve a preguntar si llegaron alertas nuevas. */
const CADA = 60_000;

/**
 * La campanita del encabezado: cuántas alertas faltan leer y las últimas.
 * Se pregunta de nuevo cada minuto a una ruta propia, sin pasar por la
 * página abierta: como recarga parcial, cada pregunta le hacía rehacer todas
 * sus consultas. Tocar una la marca como leída y lleva a donde se resuelve.
 */
export function Campanita() {
    const { campanita: deLaPagina } = usePage().props;
    const conSesion = deLaPagina !== null;

    // Lo último que respondió el sondeo, junto a los datos de página que
    // había entonces: cuando la página trae datos nuevos (una visita, marcar
    // como leídas), mandan esos y lo sondeado queda atrás.
    const [sondeo, setSondeo] = useState<{
        base: DatosCampanita | null;
        datos: DatosCampanita;
    } | null>(null);

    // En un ref para que el reloj del sondeo no se reinicie con cada
    // navegación.
    const base = useRef(deLaPagina);

    useEffect(() => {
        base.current = deLaPagina;
    }, [deLaPagina]);

    useEffect(() => {
        if (!conSesion) {
            return;
        }

        const preguntar = () => {
            // Con la pestaña oculta no se pregunta: nadie la está mirando.
            if (document.hidden) {
                return;
            }

            fetch(sondearCampanita.url(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((respuesta) =>
                    respuesta.ok ? respuesta.json() : Promise.reject(),
                )
                .then((datos: DatosCampanita) =>
                    setSondeo({ base: base.current, datos }),
                )
                // Sin red o con la sesión vencida: queda lo que ya había.
                .catch(() => {});
        };

        const intervalo = window.setInterval(preguntar, CADA);
        // Al volver a la pestaña se pregunta de una vez, sin esperar el minuto.
        document.addEventListener('visibilitychange', preguntar);

        return () => {
            window.clearInterval(intervalo);
            document.removeEventListener('visibilitychange', preguntar);
        };
    }, [conSesion]);

    const campanita = sondeo?.base === deLaPagina ? sondeo.datos : deLaPagina;

    if (campanita === null) {
        return null;
    }

    const sinLeer = campanita.sin_leer;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        sinLeer > 0 ? `Alertas: ${sinLeer} sin leer` : 'Alertas'
                    }
                >
                    <Bell className="size-5" />
                    {sinLeer > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white tabular-nums">
                            {sinLeer > 99 ? '99+' : sinLeer}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-[min(22rem,calc(100vw-2rem))] p-0"
            >
                <div className="flex items-center justify-between border-b px-4 py-2.5">
                    <p className="text-sm font-semibold">Alertas</p>
                    {sinLeer > 0 && (
                        <Link
                            href={leerTodas()}
                            as="button"
                            preserveScroll
                            className="text-xs text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Marcar todas como leídas
                        </Link>
                    )}
                </div>

                {campanita.recientes.length === 0 ? (
                    <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                        No tienes alertas.
                    </p>
                ) : (
                    <ul className="max-h-[60vh] divide-y overflow-y-auto">
                        {campanita.recientes.map((alerta) => (
                            <li key={alerta.id}>
                                <FilaAlerta alerta={alerta} />
                            </li>
                        ))}
                    </ul>
                )}

                <Link
                    href={bandeja()}
                    className="block border-t px-4 py-2.5 text-center text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground"
                >
                    Ver todas
                </Link>
            </PopoverContent>
        </Popover>
    );
}

/** Una alerta: sin leer va resaltada y con un punto. */
export function FilaAlerta({ alerta }: { alerta: Alerta }) {
    return (
        <Link
            href={abrir(alerta.id)}
            as="button"
            className={cn(
                'flex w-full gap-3 px-4 py-3 text-left hover:bg-accent',
                !alerta.leida && 'bg-primary/5',
            )}
        >
            <span
                className={cn(
                    'mt-1.5 size-2 shrink-0 rounded-full',
                    alerta.leida ? 'bg-transparent' : 'bg-red-600',
                )}
                aria-hidden
            />
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block text-sm',
                        alerta.leida ? 'text-muted-foreground' : 'font-medium',
                    )}
                >
                    {alerta.titulo}
                </span>
                {alerta.mensaje && (
                    <span className="mt-0.5 line-clamp-2 block text-xs whitespace-pre-line text-muted-foreground">
                        {alerta.mensaje}
                    </span>
                )}
                <span className="mt-1 block text-[11px] text-muted-foreground/70">
                    {alerta.tipo_label} · {haceCuanto(alerta.fecha)}
                </span>
            </span>
        </Link>
    );
}
