import { Eye, MoreVertical, Trash2, Upload } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { estiloDocumento } from '@/lib/documentos';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RanuraDocumental } from '@/types/fleet';

type Props = {
    ranura: RanuraDocumental;
    puedeGestionar: boolean;
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
    puedeGestionar,
    onEliminar,
    renderCargar,
}: Props) {
    const { documento, estado } = ranura;
    const tema = estiloDocumento[estado];

    return (
        <tr className="border-b last:border-0 hover:bg-muted/40">
            <td className="max-w-0 p-3">
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
            </td>

            <td className="p-3 text-sm whitespace-nowrap tabular-nums">
                {documento?.fecha_vencimiento ? (
                    <span
                        className={cn(
                            estado === 'vencido' &&
                                'text-red-700 dark:text-red-400',
                            estado === 'por_vencer' &&
                                'text-amber-700 dark:text-amber-500',
                        )}
                    >
                        {formatearFecha(documento.fecha_vencimiento)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>

            <td className="p-3">
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
            </td>

            <td className="p-3">
                <div className="flex items-center justify-end gap-0.5">
                    {documento === null
                        ? puedeGestionar &&
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

                            {puedeGestionar && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        title={`Acciones de ${ranura.label}`}
                                        aria-label={`Acciones de ${ranura.label}`}
                                        className="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <MoreVertical className="size-4" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
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
            </td>
        </tr>
    );
}
