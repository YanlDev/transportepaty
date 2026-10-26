import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/react';
import { CalendarPlus, Pencil, Search, Truck } from 'lucide-react';
import { useMemo, useState } from 'react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AvisosVuelta } from '@/components/vueltas/avisos-vuelta';
import { DetalleVuelta } from '@/components/vueltas/detalle-vuelta';
import { KanbanVueltas } from '@/components/vueltas/kanban-vueltas';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import {
    EstadoVueltaBadge,
    TramosVuelta,
} from '@/components/vueltas/tramos-vuelta';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    DiaDeSemana,
    OpcionesVuelta,
    SalidaDelDia,
    TarjetaVuelta,
    Vuelta,
} from '@/types/vueltas';

type Props = OpcionesVuelta & {
    fecha: string;
    semana: DiaDeSemana[];
    /** Las vueltas que salen el día elegido, con sus avisos. */
    salidas: SalidaDelDia[];
    /** El kanban: cada vuelta abierta en su etapa y las recién cerradas. */
    tablero: TarjetaVuelta[];
    whatsappConectado: boolean;
};

type Vista = 'tablero' | 'salidas';

/** El diálogo abierto: para programar una nueva o editar una existente. */
type Dialogo = { vuelta: Vuelta | null } | null;

const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

const sinGuion = (texto: string) => texto.toLowerCase().replace(/-/g, '');

/**
 * La programación: todo nace acá. El tablero muestra cada vuelta en su
 * etapa (programada, bajando, subiendo, cerrada); las salidas del día son
 * desde donde se avisa por WhatsApp al conductor y a las áreas.
 */
export default function Programacion({
    fecha,
    semana,
    salidas,
    tablero,
    whatsappConectado,
    ...opciones
}: Props) {
    const { puede } = usePermisos();
    const [vista, setVista] = useState<Vista>('tablero');
    const [buscar, setBuscar] = useState('');
    const [dialogo, setDialogo] = useState<Dialogo>(null);
    const [abiertaId, setAbiertaId] = useState<number | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Programación', href: vueltas.index().url }],
    });

    // Cada 15 segundos: las vueltas cambian de columna solas cuando llegan
    // sus GR, y el ✓✓ de un aviso aparece sin recargar.
    usePoll(15_000, { only: ['tablero', 'salidas', 'semana'] });

    const irA = (nuevaFecha: string) =>
        router.get(
            vueltas.index.url({ query: { fecha: nuevaFecha } }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const filtradas = useMemo(() => {
        const texto = sinGuion(buscar.trim());

        if (texto === '') {
            return tablero;
        }

        return tablero.filter(
            (vuelta) =>
                sinGuion(vuelta.placa).includes(texto) ||
                sinGuion(vuelta.codigo ?? '').includes(texto) ||
                (vuelta.conductor ?? '').toLowerCase().includes(texto),
        );
    }, [buscar, tablero]);

    // Se busca por id en lo último que llegó: si la vuelta cambia mientras el
    // panel está abierto (llega una GR, se manda un aviso), se ve al día.
    const abierta = tablero.find((vuelta) => vuelta.id === abiertaId) ?? null;

    const editar = (vuelta: Vuelta) => setDialogo({ vuelta });

    return (
        <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Programación" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="inline-flex self-start rounded-lg border p-0.5">
                    {(
                        [
                            ['tablero', 'Tablero'],
                            ['salidas', 'Salidas del día'],
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

                <div className="flex gap-2">
                    {vista === 'tablero' ? (
                        <div className="relative flex-1 sm:w-64">
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
                    ) : (
                        <Input
                            type="date"
                            value={fecha}
                            onChange={(evento) =>
                                evento.target.value && irA(evento.target.value)
                            }
                            className="w-44"
                        />
                    )}
                    {puede('programacion.crear') && (
                        <Button onClick={() => setDialogo({ vuelta: null })}>
                            <CalendarPlus className="size-4" />
                            <span className="max-sm:hidden">
                                Programar vuelta
                            </span>
                        </Button>
                    )}
                </div>
            </div>

            {vista === 'tablero' ? (
                <KanbanVueltas
                    vueltas={filtradas}
                    onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
                />
            ) : (
                <>
                    {/* La semana del día elegido, con cuántas vueltas salen
                        cada uno: se ve de un vistazo qué días ya tienen plan. */}
                    <div className="grid grid-cols-7 gap-1.5">
                        {semana.map((dia, indice) => {
                            const esElVisto = dia.fecha === fecha;

                            return (
                                <button
                                    key={dia.fecha}
                                    type="button"
                                    onClick={() => irA(dia.fecha)}
                                    aria-current={
                                        esElVisto ? 'date' : undefined
                                    }
                                    className={cn(
                                        'flex flex-col items-center gap-0.5 rounded-lg border px-1 py-2 text-xs transition',
                                        esElVisto
                                            ? 'border-primary bg-primary/10 font-semibold'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    <span className="text-muted-foreground">
                                        {DIAS_CORTOS[indice]}
                                    </span>
                                    <span className="text-sm">
                                        {Number(dia.fecha.slice(8, 10))}
                                    </span>
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 text-[10px] leading-4',
                                            dia.programadas > 0
                                                ? 'bg-primary/15 text-primary'
                                                : 'text-muted-foreground/50',
                                        )}
                                    >
                                        {dia.programadas}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {salidas.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-10 text-center">
                            <Truck className="size-7 text-muted-foreground" />
                            <p className="font-medium">
                                Sin vueltas que salgan este día
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Programa la vuelta de una unidad: es el ticket
                                del viaje.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4 xl:grid-cols-2">
                            {salidas.map((salida) => (
                                <section
                                    key={salida.id}
                                    className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                                >
                                    <header className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={
                                                vueltas.unidad(salida.tracto_id)
                                                    .url
                                            }
                                            className="font-mono text-base font-semibold hover:underline"
                                        >
                                            {formatearPlaca(salida.placa)}
                                        </Link>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {salida.codigo}
                                        </span>
                                        <EstadoVueltaBadge vuelta={salida} />
                                        <span className="truncate text-xs text-muted-foreground">
                                            {salida.conductor ??
                                                'Sin conductor'}
                                            {salida.inicio &&
                                                ` · ${formatearFecha(salida.inicio)}`}
                                        </span>
                                        {salida.editable && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="ml-auto h-7"
                                                onClick={() => editar(salida)}
                                            >
                                                <Pencil className="size-3.5" />
                                                Editar
                                            </Button>
                                        )}
                                    </header>
                                    <TramosVuelta vuelta={salida} />
                                    <AvisosVuelta
                                        salida={salida}
                                        conectado={whatsappConectado}
                                        puedeAvisar={puede(
                                            'programacion.avisar',
                                        )}
                                    />
                                </section>
                            ))}
                        </div>
                    )}
                </>
            )}

            <DetalleVuelta
                vuelta={abierta}
                onCerrar={() => setAbiertaId(null)}
                onEditar={editar}
                conectado={whatsappConectado}
                puedeAvisar={puede('programacion.avisar')}
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
