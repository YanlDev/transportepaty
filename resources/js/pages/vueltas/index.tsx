import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarPlus,
    FileStack,
    Search,
    Settings,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionProgramacionController';
import guiasRutas from '@/actions/App/Http/Controllers/GuiaPorAsignarController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DetalleVuelta } from '@/components/vueltas/detalle-vuelta';
import { GanttVueltas } from '@/components/vueltas/gantt-vueltas';
import { ListaProgramacion } from '@/components/vueltas/lista-programacion';
import { ParaAtender, pendienteDe } from '@/components/vueltas/para-atender';
import { ProgramarVueltaDialog } from '@/components/vueltas/programar-vuelta-dialog';
import { usePermisos } from '@/hooks/use-permisos';
import { CLASES, clasesDeTicket } from '@/lib/clase-carga';
import type { ClaseCarga } from '@/lib/clase-carga';
import { ETAPAS } from '@/lib/etapas';
import { cn } from '@/lib/utils';
import type {
    EtapaVuelta,
    OpcionesVuelta,
    TarjetaVuelta,
    Vuelta,
} from '@/types/vueltas';

type Props = OpcionesVuelta & {
    /** Hoy: la fecha de salida que se propone al programar. */
    fecha: string;
    /** Cada ticket abierto en su etapa, y las unidades en base. */
    tablero: TarjetaVuelta[];
    /** Las GR que todavía no están en ningún ticket. */
    guiasPorAsignar: number;
};

type Vista = 'lista' | 'gantt';

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
 * La programación: todo nace acá. Abre con lo que hay que atender; debajo,
 * cada ticket por etapa —por salir, en ida, en vuelta, en base— como lista
 * (el más nuevo arriba) o como calendario. Clic en un ticket abre su detalle.
 */
export default function Programacion({
    fecha,
    tablero,
    guiasPorAsignar,
    ...opciones
}: Props) {
    const { puede } = usePermisos();
    const [vista, setVista] = useState<Vista>('lista');
    const [etapa, setEtapa] = useState<EtapaVuelta | null>(null);
    const [clase, setClase] = useState<ClaseCarga | null>(null);
    // Un grupo de «Para atender»: deja solo los tickets que esperan eso.
    const [pendiente, setPendiente] = useState<string | null>(null);
    const [buscar, setBuscar] = useState('');
    const [dialogo, setDialogo] = useState<Dialogo>(null);
    const [abiertaId, setAbiertaId] = useState<number | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: 'Programación', href: vueltas.index().url }],
    });

    // Cada 15 segundos: lo que otras áreas registran (GR asignadas, OK de
    // taller, llegadas) se ve sin recargar.
    usePoll(15_000, { only: ['tablero', 'guiasPorAsignar'] });

    const filtradas = useMemo(
        () => filtrar(tablero, buscar),
        [buscar, tablero],
    );
    const deClase = (vuelta: TarjetaVuelta, buscada: ClaseCarga) =>
        clasesDeTicket([...vuelta.ida, ...vuelta.regreso]).includes(buscada);
    const deLaClase = filtradas.filter(
        (vuelta) => clase === null || deClase(vuelta, clase),
    );
    const visibles = deLaClase.filter(
        (vuelta) =>
            (etapa === null || vuelta.etapa === etapa) &&
            (pendiente === null || pendienteDe(vuelta) === pendiente),
    );

    // Se busca por id en lo último que llegó: si el ticket cambia con el
    // panel abierto (llega una GR), se ve al día.
    const abierta = tablero.find((vuelta) => vuelta.id === abiertaId) ?? null;

    const editar = (vuelta: Vuelta) => setDialogo({ vuelta });

    return (
        <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Programación" />

            <EncabezadoPantalla
                titulo="Programación"
                descripcion="Cada viaje es un ticket: aquí se programa y se ve qué le falta a cada uno."
                icono={CalendarCheck}
                acciones={
                    <>
                        <div className="relative flex-1 sm:w-64 sm:flex-none">
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
                        <Button variant="outline" asChild>
                            <Link href={guiasRutas.index().url}>
                                <FileStack className="size-4" />
                                <span className="max-sm:hidden">
                                    GR por asignar
                                </span>
                                {guiasPorAsignar > 0 && (
                                    <span className="rounded-full bg-foreground px-1.5 text-xs font-semibold text-background tabular-nums">
                                        {guiasPorAsignar}
                                    </span>
                                )}
                            </Link>
                        </Button>
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
                            <Button
                                onClick={() => setDialogo({ vuelta: null })}
                            >
                                <CalendarPlus className="size-4" />
                                <span className="max-sm:hidden">
                                    Nuevo ticket
                                </span>
                            </Button>
                        )}
                    </>
                }
            />

            <ParaAtender
                vueltas={deLaClase}
                guiasPorAsignar={guiasPorAsignar}
                filtro={pendiente}
                onFiltrar={(clave) => {
                    setPendiente(clave);
                    setEtapa(null);
                }}
            />

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                {/* Las etapas como pestañas, igual que la ida y la vuelta de
                    abastecimiento. */}
                <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                    <div className="inline-flex rounded-lg border p-0.5">
                        {[
                            { etapa: null, titulo: 'Todos', punto: null },
                            ...ETAPAS,
                        ].map((opcion) => {
                            const activa = etapa === opcion.etapa;

                            return (
                                <button
                                    key={opcion.etapa ?? 'todos'}
                                    type="button"
                                    onClick={() => {
                                        setEtapa(opcion.etapa);
                                        setPendiente(null);
                                    }}
                                    aria-pressed={activa}
                                    className={cn(
                                        'flex min-h-9 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition pointer-coarse:min-h-11',
                                        activa
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {opcion.punto && (
                                        <span
                                            className={cn(
                                                'size-2 rounded-full',
                                                opcion.punto,
                                            )}
                                        />
                                    )}
                                    {opcion.titulo} ·{' '}
                                    {
                                        deLaClase.filter(
                                            (vuelta) =>
                                                opcion.etapa === null ||
                                                vuelta.etapa === opcion.etapa,
                                        ).length
                                    }
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* Mina o particular: un ticket con las dos cuenta en
                        las dos. Se combina con la etapa. */}
                    {CLASES.map((opcion) => {
                        const activa = clase === opcion.clase;

                        return (
                            <button
                                key={opcion.clase}
                                type="button"
                                onClick={() =>
                                    setClase(activa ? null : opcion.clase)
                                }
                                aria-pressed={activa}
                                data-filtro-clase={opcion.clase}
                                className={cn(
                                    'min-h-9 rounded-full border px-3 text-sm transition pointer-coarse:min-h-11',
                                    activa
                                        ? 'border-primary bg-accent font-medium text-accent-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {opcion.titulo} ·{' '}
                                {
                                    filtradas.filter((vuelta) =>
                                        deClase(vuelta, opcion.clase),
                                    ).length
                                }
                            </button>
                        );
                    })}
                    <div className="inline-flex rounded-lg border p-0.5 max-md:hidden">
                        {(
                            [
                                ['lista', 'Lista'],
                                ['gantt', 'Calendario'],
                            ] as const
                        ).map(([valor, etiqueta]) => (
                            <button
                                key={valor}
                                type="button"
                                onClick={() => setVista(valor)}
                                aria-pressed={vista === valor}
                                className={cn(
                                    'min-h-9 rounded-md px-3 py-1.5 text-sm transition',
                                    vista === valor
                                        ? 'bg-muted font-medium text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {etiqueta}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {pendiente !== null && (
                <p className="-mb-2 flex items-center gap-2 text-sm text-muted-foreground">
                    Solo los de «Para atender» que elegiste.
                    <button
                        type="button"
                        onClick={() => setPendiente(null)}
                        className="font-medium text-primary hover:underline"
                    >
                        Ver todos
                    </button>
                </p>
            )}

            {vista === 'gantt' ? (
                <GanttVueltas
                    vueltas={visibles}
                    onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
                />
            ) : (
                <ListaProgramacion
                    vueltas={visibles}
                    onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
                    onEditar={editar}
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
