import { Eye, MoreVertical, Pencil, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TableCell, TableRow } from '@/components/ui/table';
import { ValorEditable } from '@/components/valor-editable';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { estiloDocumento } from '@/lib/documentos';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RanuraDocumental } from '@/types/fleet';

type Props = {
    ranura: RanuraDocumental;
    puedeEditar: boolean;
    /**
     * A dónde mandar la corrección del vencimiento. Null cuando la ranura está
     * vacía: primero hay que cargar el documento.
     */
    urlVencimiento: string | null;
    /** Se llama al confirmar el borrado del documento cargado. */
    onEliminar: () => void;
    /**
     * Envuelve el botón de carga en el diálogo que corresponda. Cada dominio
     * —vehículo, conductor— tiene el suyo, así que la fila pone el botón y
     * quien la usa pone el diálogo.
     */
    renderCargar: (trigger: React.ReactNode) => React.ReactNode;
};

/**
 * Un documento como fila de tabla. Es la misma información que
 * `DocumentoTarjeta`, pero en formato tabla: se usa donde hay ancho y varios
 * documentos con vencimiento que conviene comparar de una columna —el
 * expediente del vehículo—, mientras la tarjeta queda para la barra lateral
 * del conductor y para el móvil.
 */
export function DocumentoFila({
    ranura,
    puedeEditar,
    urlVencimiento,
    onEliminar,
    renderCargar,
}: Props) {
    const { documento, estado } = ranura;
    const tema = estiloDocumento[estado];

    // La edición del vencimiento vive en la celda, pero también se ofrece
    // desde el menú de acciones: es donde se busca «editar», y la fecha sola
    // no alcanzaba a comunicar que se podía tocar.
    const [editandoVencimiento, setEditandoVencimiento] = useState(false);

    return (
        <TableRow>
            <TableCell className="max-w-0">
                <p
                    className={cn(
                        'truncate text-sm',
                        documento === null && 'text-muted-foreground',
                    )}
                    title={ranura.label}
                >
                    {ranura.label}
                </p>
                {documento?.numero && (
                    <p className="truncate font-mono text-xs text-muted-foreground">
                        {documento.numero}
                    </p>
                )}
            </TableCell>

            <TableCell className="text-sm whitespace-nowrap tabular-nums">
                {documento !== null &&
                puedeEditar &&
                urlVencimiento !== null ? (
                    <ValorEditable
                        url={urlVencimiento}
                        campo="fecha_vencimiento"
                        valor={documento.fecha_vencimiento}
                        tipo="fecha"
                        editable
                        etiqueta={`el vencimiento de ${ranura.label}`}
                        placeholder="Sin vencimiento"
                        ancho="min-w-32"
                        editando={editandoVencimiento}
                        onEditandoChange={setEditandoVencimiento}
                        className="group/fecha flex items-center gap-1.5"
                    >
                        {documento.fecha_vencimiento === null ? null : (
                            <FechaVencimiento
                                fecha={documento.fecha_vencimiento}
                                estado={estado}
                            />
                        )}
                        <Pencil
                            aria-hidden
                            className="size-3 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/fecha:opacity-100"
                        />
                    </ValorEditable>
                ) : documento?.fecha_vencimiento ? (
                    <FechaVencimiento
                        fecha={documento.fecha_vencimiento}
                        estado={estado}
                    />
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>

            <TableCell>
                <span
                    className={cn(
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap',
                        tema.chip,
                    )}
                >
                    <span
                        className={cn('size-1.5 rounded-full', tema.punto)}
                        aria-hidden
                    />
                    {ranura.estado_label}
                </span>
            </TableCell>

            <TableCell>
                <div className="flex items-center justify-end gap-0.5">
                    {documento === null
                        ? puedeEditar &&
                          renderCargar(
                              <button
                                  type="button"
                                  title={`Cargar ${ranura.label}`}
                                  aria-label={`Cargar ${ranura.label}`}
                                  className="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-primary hover:text-primary-foreground"
                              >
                                  <Upload className="size-4" />
                              </button>,
                          )
                        : null}

                    {documento !== null && (
                        <>
                            <DocumentoVisorDialog
                                url={documento.url}
                                esPdf={documento.es_pdf}
                                titulo={ranura.label}
                                detalle={[
                                    documento.numero,
                                    documento.fecha_vencimiento
                                        ? `Vence ${formatearFecha(documento.fecha_vencimiento)}`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                                trigger={
                                    <button
                                        type="button"
                                        title={`Ver ${ranura.label}`}
                                        aria-label={`Ver ${ranura.label}`}
                                        className="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <Eye className="size-4" />
                                    </button>
                                }
                            />

                            {puedeEditar && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        title={`Acciones de ${ranura.label}`}
                                        aria-label={`Acciones de ${ranura.label}`}
                                        className="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <MoreVertical className="size-4" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {urlVencimiento !== null && (
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setEditandoVencimiento(true)
                                                }
                                            >
                                                <Pencil className="size-4" />
                                                Editar vencimiento
                                            </DropdownMenuItem>
                                        )}
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={onEliminar}
                                        >
                                            <Trash2 className="size-4" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </>
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}

/** La fecha con el color de su situación: rojo si venció, ámbar si está cerca. */
function FechaVencimiento({
    fecha,
    estado,
}: {
    fecha: string;
    estado: RanuraDocumental['estado'];
}) {
    return (
        <span
            className={cn(
                estado === 'vencido' && 'text-red-700 dark:text-red-400',
                estado === 'por_vencer' && 'text-amber-700 dark:text-amber-500',
            )}
        >
            {formatearFecha(fecha)}
        </span>
    );
}
