import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import asistencia, {
    destroy,
    marcar,
} from '@/actions/App/Http/Controllers/AsistenciaController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

const estadoConfig: Record<
    EstadoAsistencia,
    { label: string; letra: string; badge: string }
> = {
    asistencia: {
        label: 'Asistencia',
        letra: 'A',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    falta: {
        label: 'Falta',
        letra: 'F',
        badge: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    },
    vacaciones: {
        label: 'Vacaciones',
        letra: 'V',
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    descanso: {
        label: 'Descanso',
        letra: 'D',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
};

/**
 * Celda estilo Excel: borde derecho e inferior. Solo esos dos lados —el
 * contorno completo lo cierra el borde del contenedor— porque con
 * `border-collapse` el `sticky` de las celdas deja de funcionar en varios
 * navegadores; con `border-separate` cada celda pinta su propio borde y
 * bordear los cuatro lados duplicaría la línea entre celda y celda.
 */
const CELDA_CON_BORDE = 'border-r border-b border-border';

export default function AsistenciaIndex({ inicioCiclo, dias, filas }: Props) {
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

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Asistencia" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Asistencia
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Rooster del ciclo de planilla (30 días, del 28 al 27):
                        quién trabajó, faltó, o está de vacaciones o descanso.
                        Click en una celda para marcarla.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => sumarCiclos(-1)}
                        aria-label="Ciclo anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="min-w-[9rem] text-center text-sm font-medium tabular-nums">
                        {rangoCiclo}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => sumarCiclos(1)}
                        aria-label="Ciclo siguiente"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
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

            <div className="max-h-[75vh] overflow-auto border border-border">
                <Table className="border-separate border-spacing-0">
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead
                                className={cn(
                                    CELDA_CON_BORDE,
                                    'sticky top-0 left-0 z-30 min-w-[180px] bg-background',
                                )}
                            >
                                Conductor
                            </TableHead>
                            {dias.map((dia) => (
                                <TableHead
                                    key={dia.fecha}
                                    className={cn(
                                        CELDA_CON_BORDE,
                                        'sticky top-0 z-20 w-9 min-w-9 bg-background p-0 text-center',
                                        dia.es_domingo && 'bg-muted/60',
                                    )}
                                >
                                    <div className="flex flex-col items-center leading-tight">
                                        <span className="text-[10px] text-muted-foreground">
                                            {dia.dia_semana}
                                        </span>
                                        <span className="tabular-nums">
                                            {dia.numero}
                                        </span>
                                    </div>
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filas.map((fila) => (
                            <TableRow key={fila.conductor_id}>
                                <TableCell
                                    className={cn(
                                        CELDA_CON_BORDE,
                                        'sticky left-0 z-10 bg-background font-medium tracking-wide whitespace-nowrap uppercase',
                                    )}
                                >
                                    {fila.nombre_completo}
                                </TableCell>
                                {dias.map((dia) => (
                                    <TableCell
                                        key={dia.fecha}
                                        className={cn(
                                            CELDA_CON_BORDE,
                                            'p-0.5 text-center',
                                            dia.es_domingo && 'bg-muted/40',
                                        )}
                                    >
                                        <Celda
                                            conductorId={fila.conductor_id}
                                            fecha={dia.fecha}
                                            marca={fila.marcas[dia.fecha]}
                                        />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

/** «28 ene» a partir de una fecha Y-m-d, para el rango del ciclo. */
function formatearCorto(fecha: string): string {
    const d = new Date(`${fecha}T00:00:00`);

    return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
}

function Celda({
    conductorId,
    fecha,
    marca,
}: {
    conductorId: number;
    fecha: string;
    marca: AsistenciaFila['marcas'][string] | undefined;
}) {
    const info = marca ? estadoConfig[marca.estado] : null;

    const marcarComo = (estado: EstadoAsistencia) => {
        router.patch(
            marcar(conductorId).url,
            { fecha, estado },
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
                className={cn(
                    'mx-auto grid size-7 cursor-pointer place-items-center text-xs font-bold hover:ring-1 hover:ring-foreground/30 hover:ring-inset',
                    info ? info.badge : 'text-transparent',
                )}
                title={info ? info.label : 'Sin marcar'}
            >
                {info ? info.letra : '·'}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="center">
                {(Object.keys(estadoConfig) as EstadoAsistencia[]).map(
                    (estado) => (
                        <DropdownMenuItem
                            key={estado}
                            onSelect={() => marcarComo(estado)}
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
                        </DropdownMenuItem>
                    ),
                )}
                {marca && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={quitarMarca}
                            className="text-muted-foreground"
                        >
                            Quitar marca
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

AsistenciaIndex.layout = {
    breadcrumbs: [{ title: 'Asistencia', href: asistencia.index().url }],
};
