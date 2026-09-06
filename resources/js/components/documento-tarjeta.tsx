import { Eye, FileText, MoreVertical, Trash2, Upload } from 'lucide-react';
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
     * —vehículo, conductor— tiene el suyo, así que la tarjeta pone el botón y
     * quien la usa pone el diálogo.
     */
    renderCargar: (trigger: React.ReactNode) => React.ReactNode;
};

/**
 * Una ficha del expediente documental. Sirve igual para vehículos y conductores:
 * lo único propio de cada uno es a dónde apunta el botón de cargar y el de
 * eliminar, que llegan por props.
 */
export function DocumentoTarjeta({
    ranura,
    puedeGestionar,
    onEliminar,
    renderCargar,
}: Props) {
    const { documento, estado } = ranura;
    const tema = estiloDocumento[estado];

    const fecha = documento?.fecha_vencimiento
        ? `${estado === 'vencido' ? 'Venció' : 'Vence'} ${formatearFecha(documento.fecha_vencimiento)}`
        : documento
          ? 'Sin vencimiento'
          : 'No cargado';

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-lg border p-2.5 shadow-sm transition-all hover:shadow-md',
                tema.tarjeta,
            )}
        >
            <span
                className={cn(
                    'grid size-9 shrink-0 place-items-center rounded-md',
                    tema.icono,
                )}
                aria-hidden
            >
                <FileText className="size-4.5" />
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm leading-tight font-medium text-foreground">
                    {ranura.label}
                </p>
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {documento?.numero && (
                        <span className="font-mono">{documento.numero} · </span>
                    )}
                    <span className="tabular-nums">{fecha}</span>
                </p>
            </div>

            <span
                className={cn(
                    'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                    tema.chip,
                )}
            >
                {ranura.estado_label}
            </span>

            <div className="flex shrink-0 items-center gap-0.5">
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

                        {/* Eliminar vive en el menú y no como un botón suelto:
                            es destructivo y no hace falta tenerlo a un toque de
                            distancia mientras se revisa el expediente. */}
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
        </div>
    );
}
