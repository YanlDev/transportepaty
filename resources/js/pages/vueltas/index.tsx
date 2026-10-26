import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/react';
import {
    CalendarPlus,
    ChevronRight,
    Pencil,
    Search,
    Truck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AvisosVuelta } from '@/components/vueltas/avisos-vuelta';
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
    UnidadConVuelta,
    Vuelta,
} from '@/types/vueltas';

type Props = OpcionesVuelta & {
    fecha: string;
    semana: DiaDeSemana[];
    /** Las vueltas que salen el día elegido, con sus avisos. */
    salidas: SalidaDelDia[];
    whatsappConectado: boolean;
    /** Cada unidad con la vuelta en que va y la próxima programada. */
    unidades: UnidadConVuelta[];
};

type Vista = 'salidas' | 'unidades';

/** El diálogo abierto: para programar una nueva o editar una existente. */
type Dialogo = { vuelta: Vuelta | null } | null;

const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function CabeceraVuelta({
    vuelta,
    onEditar,
}: {
    vuelta: Vuelta;
    onEditar: (vuelta: Vuelta) => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <EstadoVueltaBadge vuelta={vuelta} />
            <span className="text-xs text-muted-foreground">
                {vuelta.codigo} · {vuelta.tipo_label}
                {vuelta.inicio &&
                    ` · ${vuelta.estado === 'programada' ? 'sale' : 'desde'} ${formatearFecha(vuelta.inicio)}`}
            </span>
            {vuelta.editable && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto h-7"
                    onClick={() => onEditar(vuelta)}
                >
                    <Pencil className="size-3.5" />
                    Editar
                </Button>
            )}
        </div>
    );
}

/**
 * La programación: todo nace acá. Se programa la vuelta de cada unidad (el
 * ticket), se ve qué sale cada día y se avisa por WhatsApp al conductor y a
 * las áreas. La vista de unidades muestra en qué anda cada una.
 */
export default function Programacion({
    fecha,
    semana,
    salidas,
    whatsappConectado,
    unidades,
    ...opciones
}: Props) {
    const { puede } = usePermisos();
    const [vista, setVista] = useState<Vista>('salidas');
    const [buscar, setBuscar] = useState('');
    const [dialogo, setDialogo] = useState<Dialogo>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Programación', href: vueltas.index().url }],
    });

    // Cada 15 segundos las salidas del día: así el ✓✓ de un aviso aparece
    // solo y se ven las vueltas que programa otra persona.
    usePoll(15_000, { only: ['salidas', 'semana'] });

    const irA = (nuevaFecha: string) =>
        router.get(
            vueltas.index.url({ query: { fecha: nuevaFecha } }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const unidadesFiltradas = useMemo(() => {
        const texto = buscar.trim().toLowerCase().replace(/-/g, '');

        if (texto === '') {
            return unidades;
        }

        return unidades.filter(
            ({ tracto, vuelta, proxima }) =>
                tracto.placa.toLowerCase().replace(/-/g, '').includes(texto) ||
                (vuelta?.conductor ?? '').toLowerCase().includes(texto) ||
                (proxima?.conductor ?? '').toLowerCase().includes(texto),
        );
    }, [buscar, unidades]);

    const editar = (vuelta: Vuelta) => setDialogo({ vuelta });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Programación" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="inline-flex rounded-lg border p-0.5">
                    {(
                        [
                            ['salidas', 'Salidas del día'],
                            ['unidades', 'Unidades'],
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
                    {vista === 'salidas' ? (
                        <Input
                            type="date"
                            value={fecha}
                            onChange={(evento) =>
                                evento.target.value && irA(evento.target.value)
                            }
                            className="w-44"
                        />
                    ) : (
                        <div className="relative flex-1 sm:w-64">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={buscar}
                                onChange={(evento) =>
                                    setBuscar(evento.target.value)
                                }
                                placeholder="Placa o conductor"
                                className="pl-8"
                            />
                        </div>
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

            {vista === 'salidas' ? (
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
                                    <header className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 flex-col gap-0.5">
                                            <Link
                                                href={
                                                    vueltas.unidad(
                                                        salida.tracto_id,
                                                    ).url
                                                }
                                                className="font-mono text-base font-semibold hover:underline"
                                            >
                                                {formatearPlaca(salida.placa)}
                                            </Link>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {salida.conductor ??
                                                    'Sin conductor'}
                                                {salida.carreta &&
                                                    ` · carreta ${formatearPlaca(salida.carreta)}`}
                                            </span>
                                        </div>
                                    </header>
                                    <CabeceraVuelta
                                        vuelta={salida}
                                        onEditar={editar}
                                    />
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
            ) : unidadesFiltradas.length === 0 ? (
                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {unidades.length === 0
                        ? 'Todavía no hay vueltas.'
                        : 'Ninguna unidad coincide con la búsqueda.'}
                </p>
            ) : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {unidadesFiltradas.map(
                        ({ tracto, vueltas: total, vuelta, proxima }) => (
                            <section
                                key={tracto.id}
                                className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                            >
                                <header className="flex items-start justify-between gap-3">
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <span className="font-mono text-base font-semibold">
                                            {formatearPlaca(tracto.placa)}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {(vuelta ?? proxima)?.conductor ??
                                                'Sin conductor'}
                                        </span>
                                    </div>
                                    <Link
                                        href={vueltas.unidad(tracto.id).url}
                                        className="flex shrink-0 items-center gap-1 text-xs font-medium text-primary hover:underline"
                                    >
                                        {total}{' '}
                                        {total === 1 ? 'vuelta' : 'vueltas'}
                                        <ChevronRight className="size-3.5" />
                                    </Link>
                                </header>

                                {vuelta && (
                                    <div className="flex flex-col gap-3">
                                        <CabeceraVuelta
                                            vuelta={vuelta}
                                            onEditar={editar}
                                        />
                                        <TramosVuelta vuelta={vuelta} />
                                    </div>
                                )}

                                {proxima && (
                                    <div className="flex flex-col gap-3 border-t pt-4">
                                        <CabeceraVuelta
                                            vuelta={proxima}
                                            onEditar={editar}
                                        />
                                        <TramosVuelta vuelta={proxima} />
                                    </div>
                                )}
                            </section>
                        ),
                    )}
                </div>
            )}

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
