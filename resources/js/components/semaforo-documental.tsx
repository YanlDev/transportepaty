import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatearFecha } from '@/lib/format';
import type {
    EstadoDocumental,
    EstadoDocumento,
    ResumenDocumental,
} from '@/types/fleet';

type Semaforo = ResumenDocumental['semaforo'];

const colores: Record<Semaforo, string> = {
    verde: 'bg-emerald-500',
    ambar: 'bg-amber-500',
    rojo: 'bg-red-500',
};

const textos: Record<Semaforo, string> = {
    verde: 'text-muted-foreground',
    ambar: 'text-amber-700 dark:text-amber-500',
    rojo: 'text-red-700 dark:text-red-500',
};

/**
 * Tinte de fondo para pintar la fila entera según el semáforo. Son tonos suaves
 * a propósito: el texto de la fila tiene que seguir leyéndose y las insignias de
 * estado tienen que distinguirse encima. Incluye el hover porque `TableRow` trae
 * el suyo y hay que ganarle.
 */
export const filaSemaforo: Record<Semaforo, string> = {
    verde: 'bg-emerald-50 hover:bg-emerald-100/80 dark:bg-emerald-950/30 dark:hover:bg-emerald-950/50',
    ambar: 'bg-amber-50 hover:bg-amber-100/80 dark:bg-amber-950/30 dark:hover:bg-amber-950/50',
    rojo: 'bg-red-50 hover:bg-red-100/80 dark:bg-red-950/30 dark:hover:bg-red-950/50',
};

type Props = {
    estado: ResumenDocumental;
};

/**
 * Cuántos papeles están mal, y nada más. El detalle —cuál falta, cuál venció—
 * aparece al pasar el mouse.
 *
 * Escribir el problema completo en la celda («falta REV. TÉC · 1 por vencer»)
 * obligaba a la columna a crecer al ancho del peor caso, y ese ancho lo pagaban
 * las 25 filas del listado aunque casi todas estuvieran al día. El conteo ocupa
 * siempre lo mismo.
 *
 * Sirve igual para un vehículo suelto y para una unidad completa: se apoya solo
 * en las tres listas de problemas, que ambos traen.
 */
export function ResumenProblemas({ estado }: Props) {
    const { faltantes, vencidos, por_vencer: porVencer } = estado;
    const total = faltantes.length + vencidos.length + porVencer.length;

    if (total === 0) {
        return (
            <span className="text-xs whitespace-nowrap text-muted-foreground">
                Al día
            </span>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger
                    className={`cursor-help text-xs font-medium whitespace-nowrap ${textos[estado.semaforo]}`}
                >
                    {total} {total === 1 ? 'problema' : 'problemas'}
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">
                    <div className="flex flex-col gap-1.5 text-left">
                        <Grupo titulo="Sin cargar" documentos={faltantes} />
                        <Grupo titulo="Vencidos" documentos={vencidos} />
                        <Grupo
                            titulo="Vencen en 30 días"
                            documentos={porVencer}
                        />
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

const chipDocumento: Record<EstadoDocumento, string> = {
    vigente:
        'bg-emerald-100 text-emerald-800 ring-emerald-600/25 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-500/30',
    por_vencer:
        'bg-amber-100 text-amber-900 ring-amber-600/30 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-500/30',
    vencido:
        'bg-red-100 text-red-800 ring-red-600/30 dark:bg-red-950 dark:text-red-300 dark:ring-red-500/30',
    faltante:
        'bg-zinc-100 text-zinc-500 ring-zinc-500/25 line-through dark:bg-zinc-800 dark:text-zinc-400',
};

/**
 * Un chip por documento obligatorio, coloreado según su situación. Muestra
 * también los que están en regla —no solo los problemas— para poder responder de
 * un vistazo qué papeles tiene la unidad y cuáles le faltan.
 */
export function DocumentosResumen({ estado }: { estado: EstadoDocumental }) {
    return (
        <TooltipProvider>
            <div className="flex flex-wrap items-center gap-1">
                {estado.documentos.map((documento) => (
                    <Tooltip key={documento.tipo}>
                        <TooltipTrigger
                            className={`cursor-help px-1.5 py-0.5 text-[10px] font-semibold tracking-wide ring-1 ring-inset ${chipDocumento[documento.estado]}`}
                        >
                            {documento.abreviatura}
                        </TooltipTrigger>
                        <TooltipContent>
                            <p className="font-medium">{documento.label}</p>
                            <p>
                                {documento.estado_label}
                                {documento.vence &&
                                    ` · ${formatearFecha(documento.vence)}`}
                            </p>
                        </TooltipContent>
                    </Tooltip>
                ))}
            </div>
        </TooltipProvider>
    );
}

/**
 * Explica qué significa cada color de fila. Sin esto el listado es un muro de
 * colores sin clave de lectura.
 */
export function LeyendaSemaforo() {
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <Clave color="verde" texto="Documentación al día" />
            <Clave color="ambar" texto="Vence en 30 días o menos" />
            <Clave color="rojo" texto="Falta un documento o ya venció" />
        </div>
    );
}

function Clave({ color, texto }: { color: Semaforo; texto: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={`size-2.5 shrink-0 ${colores[color]}`} />
            {texto}
        </span>
    );
}

function Grupo({
    titulo,
    documentos,
}: {
    titulo: string;
    documentos: string[];
}) {
    if (documentos.length === 0) {
        return null;
    }

    return (
        <div>
            <p className="font-medium">{titulo}</p>
            <ul className="list-inside list-disc">
                {documentos.map((documento) => (
                    <li key={documento}>{documento}</li>
                ))}
            </ul>
        </div>
    );
}
