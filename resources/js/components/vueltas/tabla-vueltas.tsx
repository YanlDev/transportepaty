import { ArrowDown, ArrowUp } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Placa } from '@/components/placa';
import { IconoTaller } from '@/components/taller/estado-taller';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ETAPAS } from '@/lib/etapas';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EtapaVuelta, TarjetaVuelta } from '@/types/vueltas';

// Las etapas viven en `@/lib/etapas`: se reexportan para quien ya las
// tomaba de acá.
export { ETAPAS } from '@/lib/etapas';

type Columna = 'placa' | 'codigo' | 'conductor' | 'fecha' | 'dias';

/** Cuánto lleva: en ruta, los días del ticket; en base, los de espera. */
const dias = (vuelta: TarjetaVuelta): number =>
    vuelta.dias_en_ruta ?? vuelta.dias_en_base ?? -1;

const VALOR: Record<Columna, (vuelta: TarjetaVuelta) => string | number> = {
    placa: (vuelta) => vuelta.placa,
    codigo: (vuelta) => vuelta.codigo ?? '',
    conductor: (vuelta) => vuelta.conductor ?? '',
    fecha: (vuelta) => vuelta.actual?.fecha ?? vuelta.inicio ?? '',
    dias,
};

function Encabezado({
    columna,
    titulo,
    orden,
    onOrdenar,
    className,
}: {
    columna: Columna;
    titulo: string;
    orden: { columna: Columna; asc: boolean };
    onOrdenar: (columna: Columna) => void;
    className?: string;
}) {
    const activa = orden.columna === columna;

    return (
        <TableHead className={className}>
            <button
                type="button"
                onClick={() => onOrdenar(columna)}
                className={cn(
                    'inline-flex items-center gap-1 hover:text-foreground',
                    activa && 'text-foreground',
                )}
            >
                {titulo}
                {activa &&
                    (orden.asc ? (
                        <ArrowUp className="size-3" />
                    ) : (
                        <ArrowDown className="size-3" />
                    ))}
            </button>
        </TableHead>
    );
}

/**
 * La programación como tabla: una fila por unidad, agrupada por etapa (por
 * salir, en ida, en vuelta, en base) y ordenable por columna. Clic en la
 * fila abre el detalle del ticket.
 */
export function TablaVueltas({
    vueltas,
    onAbrir,
    agrupar = true,
}: {
    vueltas: TarjetaVuelta[];
    onAbrir: (vuelta: TarjetaVuelta) => void;
    /** Sin agrupar: una sola tabla (las salidas de un día). */
    agrupar?: boolean;
}) {
    const [orden, setOrden] = useState<{ columna: Columna; asc: boolean }>({
        columna: 'placa',
        asc: true,
    });

    const ordenadas = useMemo(() => {
        const valor = VALOR[orden.columna];

        return [...vueltas].sort((a, b) => {
            const x = valor(a);
            const y = valor(b);
            const comparacion =
                typeof x === 'number' && typeof y === 'number'
                    ? x - y
                    : String(x).localeCompare(String(y), 'es');

            return orden.asc ? comparacion : -comparacion;
        });
    }, [vueltas, orden]);

    const ordenar = (columna: Columna) =>
        setOrden((anterior) => ({
            columna,
            asc: anterior.columna === columna ? !anterior.asc : true,
        }));

    const grupos = agrupar
        ? ETAPAS.map((etapa) => ({
              ...etapa,
              filas: ordenadas.filter((vuelta) => vuelta.etapa === etapa.etapa),
          }))
        : [
              {
                  etapa: null,
                  titulo: null,
                  ayuda: null,
                  punto: null,
                  filas: ordenadas,
              },
          ];

    return (
        <div className="overflow-x-auto rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <Encabezado
                            columna="placa"
                            titulo="Unidad"
                            orden={orden}
                            onOrdenar={ordenar}
                            className="w-28"
                        />
                        <Encabezado
                            columna="codigo"
                            titulo="Ticket"
                            orden={orden}
                            onOrdenar={ordenar}
                            className="w-24"
                        />
                        <Encabezado
                            columna="conductor"
                            titulo="Conductor"
                            orden={orden}
                            onOrdenar={ordenar}
                        />
                        <TableHead>Dónde va</TableHead>
                        <TableHead className="w-28">Carga</TableHead>
                        <Encabezado
                            columna="fecha"
                            titulo="Fecha"
                            orden={orden}
                            onOrdenar={ordenar}
                            className="w-24"
                        />
                        <Encabezado
                            columna="dias"
                            titulo="Días"
                            orden={orden}
                            onOrdenar={ordenar}
                            className="w-28"
                        />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {grupos.map((grupo) => (
                        <GrupoFilas
                            key={grupo.etapa ?? 'todas'}
                            grupo={grupo}
                            onAbrir={onAbrir}
                        />
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function GrupoFilas({
    grupo,
    onAbrir,
}: {
    grupo: {
        etapa: EtapaVuelta | null;
        titulo: string | null;
        ayuda: string | null;
        punto: string | null;
        filas: TarjetaVuelta[];
    };
    onAbrir: (vuelta: TarjetaVuelta) => void;
}) {
    return (
        <>
            {grupo.titulo && (
                <TableRow className="bg-muted/50 hover:bg-muted/50">
                    <TableCell colSpan={7} className="py-2">
                        <span className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'size-2 rounded-full',
                                    grupo.punto,
                                )}
                            />
                            <span className="text-xs font-semibold tracking-wide uppercase">
                                {grupo.titulo}
                            </span>
                            <span className="rounded-full bg-background px-1.5 text-xs text-muted-foreground tabular-nums">
                                {grupo.filas.length}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {grupo.ayuda}
                            </span>
                        </span>
                    </TableCell>
                </TableRow>
            )}
            {grupo.filas.length === 0 && grupo.titulo && (
                <TableRow className="hover:bg-transparent">
                    <TableCell
                        colSpan={7}
                        className="py-3 text-center text-xs text-muted-foreground"
                    >
                        Ninguna
                    </TableCell>
                </TableRow>
            )}
            {grupo.filas.map((vuelta) => {
                return (
                    <TableRow
                        key={vuelta.id}
                        onClick={() => onAbrir(vuelta)}
                        className="cursor-pointer"
                    >
                        <TableCell className="whitespace-nowrap">
                            <span className="flex items-center gap-1.5">
                                <Placa placa={vuelta.placa} tamano="sm" />
                                {vuelta.etapa !== 'en_base' && (
                                    <IconoTaller revision={vuelta.taller} />
                                )}
                            </span>
                        </TableCell>
                        <TableCell className="font-mono text-xs text-muted-foreground">
                            {vuelta.codigo}
                        </TableCell>
                        <TableCell className="max-w-48 truncate">
                            {vuelta.conductor ?? (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </TableCell>
                        <TableCell className="max-w-56 truncate">
                            {vuelta.actual?.texto ?? '—'}
                            {vuelta.etapa === 'programada' &&
                                vuelta.regreso.length === 0 && (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        · vuelta por definir
                                    </span>
                                )}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {vuelta.actual?.carga ?? '—'}
                        </TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                            {formatearFecha(
                                vuelta.actual?.fecha ?? vuelta.inicio,
                            )}
                        </TableCell>
                        <TableCell className="whitespace-nowrap tabular-nums">
                            {vuelta.dias_en_ruta !== null &&
                                `${vuelta.dias_en_ruta} ${vuelta.dias_en_ruta === 1 ? 'día' : 'días'}`}
                            {vuelta.dias_en_base !== null && (
                                <span
                                    className={cn(
                                        vuelta.dias_en_base >= 3 &&
                                            'font-medium text-amber-700 dark:text-amber-400',
                                    )}
                                >
                                    {vuelta.dias_en_base === 0
                                        ? 'llegó hoy'
                                        : `espera ${vuelta.dias_en_base} d`}
                                </span>
                            )}
                        </TableCell>
                    </TableRow>
                );
            })}
        </>
    );
}
