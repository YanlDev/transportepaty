import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import asistencia, {
    destroy,
    marcar,
} from '@/actions/App/Http/Controllers/AsistenciaController';
import { show as mostrarConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { EstadoAsistenciaOpciones } from '@/components/asistencia/estado-asistencia-opciones';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { diasSemana, estadoConfig } from '@/lib/asistencia';
import { cn } from '@/lib/utils';
import type {
    AsistenciaDia,
    AsistenciaFila,
    EstadoAsistencia,
} from '@/types/fleet';

type Props = {
    inicioCiclo: string;
    dias: AsistenciaDia[];
    filas: AsistenciaFila[];
};

export default function AsistenciaIndex({ inicioCiclo, dias, filas }: Props) {
    const [buscar, setBuscar] = useState('');

    const irAlCiclo = (nuevoInicio: string) => {
        router.get(
            asistencia.index().url,
            { inicio: nuevoInicio },
            { preserveScroll: true },
        );
    };

    const sumarCiclos = (cantidad: number) => {
        const [anio, mes, dia] = inicioCiclo.split('-').map(Number);
        const siguiente = new Date(anio, mes - 1 + cantidad, dia);
        irAlCiclo(
            `${siguiente.getFullYear()}-${String(siguiente.getMonth() + 1).padStart(2, '0')}-${String(siguiente.getDate()).padStart(2, '0')}`,
        );
    };

    const ultimoDia = dias[dias.length - 1];
    const rangoCiclo = `${formatearCorto(dias[0].fecha)} – ${formatearCorto(ultimoDia.fecha)}`;

    /*
     * La grilla va de lunes a domingo, como el calendario de la ficha del
     * conductor. El ciclo arranca un 28, que cae en cualquier día de la
     * semana, así que la primera fila lleva delante tantas celdas vacías
     * como haga falta para que cada día quede bajo su columna.
     */
    const celdasVacias = Math.max(0, diasSemana.indexOf(dias[0].dia_semana));

    const filasFiltradas = useMemo(() => {
        const termino = buscar.trim().toLowerCase();

        if (!termino) {
            return filas;
        }

        return filas.filter((fila) =>
            fila.nombre_completo.toLowerCase().includes(termino),
        );
    }, [filas, buscar]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Asistencia" />

            <p className="text-sm text-muted-foreground">
                Rooster del ciclo de planilla (del 28 al 27 del mes siguiente):
                quién trabajó, faltó, o está de vacaciones o descanso. Toca un
                día para marcarlo.
            </p>

            {/*
             * En el celular esta barra queda pegada arriba: el ciclo y el
             * buscador son lo que se toca todo el rato mientras se marca la
             * asistencia, y así no hay que volver al inicio de la lista.
             */}
            <div className="sticky top-0 z-20 -mx-4 flex flex-col gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:static md:mx-0 md:flex-row md:items-center md:justify-between md:border-0 md:bg-transparent md:px-0 md:py-0 md:backdrop-blur-none">
                <div className="flex items-center justify-between gap-2 md:justify-start">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-11 md:size-9"
                        onClick={() => sumarCiclos(-1)}
                        aria-label="Ciclo anterior"
                    >
                        <ChevronLeft className="size-5 md:size-4" />
                    </Button>
                    <span className="text-center text-sm font-medium tabular-nums md:min-w-[9rem]">
                        {rangoCiclo}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-11 md:size-9"
                        onClick={() => sumarCiclos(1)}
                        aria-label="Ciclo siguiente"
                    >
                        <ChevronRight className="size-5 md:size-4" />
                    </Button>
                </div>

                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar conductor..."
                    className="h-11 md:h-9 md:max-w-xs"
                />
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {(Object.keys(estadoConfig) as EstadoAsistencia[]).map(
                    (estado) => (
                        <span
                            key={estado}
                            className="inline-flex items-center gap-1.5"
                        >
                            <span
                                className={cn(
                                    'grid size-4 place-items-center rounded-none text-[10px] font-bold',
                                    estadoConfig[estado].badge,
                                )}
                            >
                                {estadoConfig[estado].letra}
                            </span>
                            {estadoConfig[estado].label}
                        </span>
                    ),
                )}
            </div>

            {filasFiltradas.length === 0 ? (
                <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed py-20 text-center text-sm text-muted-foreground">
                    No se encontraron conductores.
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {filasFiltradas.map((fila) => (
                        <ConductorCicloTarjeta
                            key={fila.conductor_id}
                            fila={fila}
                            dias={dias}
                            celdasVacias={celdasVacias}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

/** «28 ene» a partir de una fecha Y-m-d, para el rango del ciclo. */
function formatearCorto(fecha: string): string {
    const d = new Date(`${fecha}T00:00:00`);

    return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
}

/**
 * Un conductor y su ciclo en formato mini-calendario: reemplaza la fila de
 * la tabla gigante por una tarjeta que se lee de un vistazo, sin scroll
 * horizontal.
 */
function ConductorCicloTarjeta({
    fila,
    dias,
    celdasVacias,
}: {
    fila: AsistenciaFila;
    dias: AsistenciaDia[];
    celdasVacias: number;
}) {
    return (
        <div
            className={cn(
                'rounded-lg border border-border bg-card p-3',
                !fila.activo && 'opacity-60 grayscale',
            )}
        >
            <Link
                href={mostrarConductor(fila.conductor_id, {
                    query: { tab: 'asistencia' },
                })}
                className="mb-2 flex items-center gap-1.5 truncate text-sm font-medium tracking-wide uppercase hover:underline"
                title={fila.nombre_completo}
            >
                <span className="truncate">{fila.nombre_completo}</span>
                {!fila.activo && (
                    <span className="shrink-0 rounded-sm bg-muted px-1 py-0.5 text-[9px] font-bold tracking-normal text-muted-foreground normal-case">
                        Inactivo
                    </span>
                )}
            </Link>

            <div className="mb-1 grid grid-cols-7 gap-1">
                {diasSemana.map((letra, indice) => (
                    <span
                        key={letra}
                        className={cn(
                            'text-center text-[10px] font-medium',
                            indice === 6
                                ? 'text-foreground/70'
                                : 'text-muted-foreground/70',
                        )}
                    >
                        {letra}
                    </span>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-1">
                {Array.from({ length: celdasVacias }, (_, indice) => (
                    <div
                        key={`vacia-${indice}`}
                        className="aspect-square w-full sm:size-7"
                    />
                ))}
                {dias.map((dia) => (
                    <DiaCiclo
                        key={dia.fecha}
                        conductorId={fila.conductor_id}
                        dia={dia}
                        marca={fila.marcas[dia.fecha]}
                    />
                ))}
            </div>
        </div>
    );
}

function DiaCiclo({
    conductorId,
    dia,
    marca,
}: {
    conductorId: number;
    dia: AsistenciaDia;
    marca: AsistenciaFila['marcas'][string] | undefined;
}) {
    const info = marca ? estadoConfig[marca.estado] : null;

    const marcarComo = (estado: EstadoAsistencia) => {
        router.patch(
            marcar(conductorId).url,
            { fecha: dia.fecha, estado },
            { preserveScroll: true },
        );
    };

    const quitarMarca = () => {
        if (!marca) {
            return;
        }

        router.delete(destroy(marca.asistencia_id).url, {
            preserveScroll: true,
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                // En móvil la celda es de 40px: se marca con el pulgar sin
                // errarle al día de al lado. En escritorio se compacta.
                className={cn(
                    'grid aspect-square w-full cursor-pointer place-items-center rounded-sm text-xs font-bold tabular-nums hover:ring-1 hover:ring-foreground/30 hover:ring-inset sm:size-7 sm:text-[10px]',
                    info
                        ? info.badge
                        : cn(
                              'text-muted-foreground/70',
                              dia.es_domingo ? 'bg-muted/50' : 'bg-muted/20',
                          ),
                )}
                title={`${dia.numero} — ${info ? info.label : 'Sin marcar'}`}
            >
                {dia.numero}
            </DropdownMenuTrigger>
            <EstadoAsistenciaOpciones
                align="center"
                marca={marca}
                onSeleccionar={marcarComo}
                onQuitar={quitarMarca}
            />
        </DropdownMenu>
    );
}

AsistenciaIndex.layout = {
    breadcrumbs: [{ title: 'Asistencia', href: asistencia.index().url }],
};
