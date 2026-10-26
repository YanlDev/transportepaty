import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/react';
import { CalendarPlus, Search, Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionProgramacionController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DetalleVuelta } from '@/components/vueltas/detalle-vuelta';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import { ETAPAS, TablaVueltas } from '@/components/vueltas/tabla-vueltas';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';
import type {
    DiaDeSemana,
    OpcionesVuelta,
    TarjetaVuelta,
    Vuelta,
} from '@/types/vueltas';

type Props = OpcionesVuelta & {
    fecha: string;
    semana: DiaDeSemana[];
    /** Los tickets que salen el día elegido. */
    salidas: TarjetaVuelta[];
    /** Cada ticket abierto en su etapa, y las unidades en base. */
    tablero: TarjetaVuelta[];
    whatsappConectado: boolean;
};

type Vista = 'tablero' | 'salidas';

/** El diálogo abierto: para un ticket nuevo o para editar uno. */
type Dialogo = { vuelta: Vuelta | null } | null;

const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

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
 * —por salir, en ida, en vuelta, en base— y las salidas del día son lo que
 * sale una fecha. Clic en una fila abre el ticket con sus avisos.
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

    // Cada 15 segundos: las unidades cambian de etapa solas cuando llegan
    // sus GR, y el ✓✓ de un aviso aparece sin recargar.
    usePoll(15_000, { only: ['tablero', 'salidas', 'semana'] });

    const irA = (nuevaFecha: string) =>
        router.get(
            vueltas.index.url({ query: { fecha: nuevaFecha } }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const filtradas = useMemo(
        () => filtrar(vista === 'tablero' ? tablero : salidas, buscar),
        [buscar, vista, tablero, salidas],
    );

    // Se busca por id en lo último que llegó: si el ticket cambia con el
    // panel abierto (llega una GR, se manda un aviso), se ve al día.
    const abierta =
        [...tablero, ...salidas].find((vuelta) => vuelta.id === abiertaId) ??
        null;

    const editar = (vuelta: Vuelta) => setDialogo({ vuelta });

    return (
        <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Programación" />

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="inline-flex rounded-lg border p-0.5">
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

                    {vista === 'tablero' && (
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
                    )}
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

            {vista === 'salidas' && (
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        type="date"
                        value={fecha}
                        onChange={(evento) =>
                            evento.target.value && irA(evento.target.value)
                        }
                        className="w-44"
                    />
                    <div className="grid flex-1 grid-cols-7 gap-1.5">
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
                                        'flex items-center justify-center gap-1.5 rounded-lg border px-1 py-1.5 text-xs transition',
                                        esElVisto
                                            ? 'border-primary bg-primary/10 font-semibold'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    <span className="text-muted-foreground">
                                        {DIAS_CORTOS[indice]}
                                    </span>
                                    <span>
                                        {Number(dia.fecha.slice(8, 10))}
                                    </span>
                                    {dia.programadas > 0 && (
                                        <span className="rounded-full bg-primary/15 px-1.5 text-[10px] text-primary">
                                            {dia.programadas}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {vista === 'salidas' && filtradas.length === 0 ? (
                <p className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    No sale ningún ticket este día.
                </p>
            ) : (
                <TablaVueltas
                    vueltas={filtradas}
                    agrupar={vista === 'tablero'}
                    onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
                />
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
