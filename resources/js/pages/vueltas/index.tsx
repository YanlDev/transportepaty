import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { CalendarPlus, Search, Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionProgramacionController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DetalleVuelta } from '@/components/vueltas/detalle-vuelta';
import { GanttVueltas } from '@/components/vueltas/gantt-vueltas';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import { TablaVueltas } from '@/components/vueltas/tabla-vueltas';
import { usePermisos } from '@/hooks/use-permisos';
import { ETAPAS } from '@/lib/etapas';
import { cn } from '@/lib/utils';
import type { OpcionesVuelta, TarjetaVuelta, Vuelta } from '@/types/vueltas';

type Props = OpcionesVuelta & {
    /** Hoy: la fecha de salida que se propone al programar. */
    fecha: string;
    /** Cada ticket abierto en su etapa, y las unidades en base. */
    tablero: TarjetaVuelta[];
};

type Vista = 'gantt' | 'tablero';

/** El diálogo abierto: para un ticket nuevo o para editar uno. */
type Dialogo = { vuelta: Vuelta | null } | null;

const sinGuion = (texto: string) => texto.toLowerCase().replace(/-/g, '');

function filtrar(lista: TarjetaVuelta[], buscar: string): TarjetaVuelta[] {
    const texto = sinGuion(buscar.trim());

    if (texto === '') {
        return lista;
    }

    return lista.filter(
        (vuelta) =>
            sinGuion(vuelta.placa).includes(texto) ||
            sinGuion(vuelta.codigo ?? '').includes(texto) ||
            (vuelta.conductor ?? '').toLowerCase().includes(texto),
    );
}

/**
 * La programación: todo nace acá. El tablero lista cada unidad en su etapa
 * —por salir, en ida, en vuelta, en base— como Gantt o como lista. Clic en
 * un ticket abre su detalle.
 */
export default function Programacion({ fecha, tablero, ...opciones }: Props) {
    const { puede } = usePermisos();
    const [vista, setVista] = useState<Vista>('gantt');
    const [buscar, setBuscar] = useState('');
    const [dialogo, setDialogo] = useState<Dialogo>(null);
    const [abiertaId, setAbiertaId] = useState<number | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Programación', href: vueltas.index().url }],
    });

    // Cada 15 segundos: las unidades cambian de etapa solas cuando llegan
    // sus GR.
    usePoll(15_000, { only: ['tablero'] });

    const filtradas = useMemo(
        () => filtrar(tablero, buscar),
        [buscar, tablero],
    );

    // Se busca por id en lo último que llegó: si el ticket cambia con el
    // panel abierto (llega una GR), se ve al día.
    const abierta = tablero.find((vuelta) => vuelta.id === abiertaId) ?? null;

    const editar = (vuelta: Vuelta) => setDialogo({ vuelta });

    return (
        <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Programación" />

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="inline-flex rounded-lg border p-0.5">
                        {(
                            [
                                ['gantt', 'Gantt'],
                                ['tablero', 'Lista'],
                            ] as const
                        ).map(([valor, etiqueta]) => (
                            <button
                                key={valor}
                                type="button"
                                onClick={() => setVista(valor)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm transition',
                                    vista === valor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {etiqueta}
                            </button>
                        ))}
                    </div>

                    {
                        <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                            {ETAPAS.map((etapa) => (
                                <span
                                    key={etapa.etapa}
                                    className="flex items-center gap-1.5"
                                >
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            etapa.punto,
                                        )}
                                    />
                                    {etapa.titulo}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {
                                            tablero.filter(
                                                (vuelta) =>
                                                    vuelta.etapa ===
                                                    etapa.etapa,
                                            ).length
                                        }
                                    </span>
                                </span>
                            ))}
                        </div>
                    }
                </div>

                <div className="flex gap-2">
                    <div className="relative flex-1 lg:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={buscar}
                            onChange={(evento) =>
                                setBuscar(evento.target.value)
                            }
                            placeholder="Placa, ticket o conductor"
                            className="pl-8"
                        />
                    </div>
                    {puede('programacion.configurar') && (
                        <Button variant="outline" size="icon" asChild>
                            <Link
                                href={configuracion.index().url}
                                aria-label="Configurar la programación"
                            >
                                <Settings className="size-4" />
                            </Link>
                        </Button>
                    )}
                    {puede('programacion.crear') && (
                        <Button onClick={() => setDialogo({ vuelta: null })}>
                            <CalendarPlus className="size-4" />
                            <span className="max-sm:hidden">Nuevo ticket</span>
                        </Button>
                    )}
                </div>
            </div>

            {vista === 'gantt' ? (
                <GanttVueltas
                    vueltas={filtradas}
                    onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
                />
            ) : (
                <TablaVueltas
                    vueltas={filtradas}
                    onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
                />
            )}

            <DetalleVuelta
                vuelta={abierta}
                onCerrar={() => setAbiertaId(null)}
                onEditar={editar}
            />

            {dialogo && (
                <ProgramarVueltaDialog
                    key={dialogo.vuelta?.id ?? 'nueva'}
                    open
                    onOpenChange={(abierto) => !abierto && setDialogo(null)}
                    vuelta={dialogo.vuelta}
                    fechaInicial={fecha}
                    opciones={opciones}
                />
            )}
        </div>
    );
}
