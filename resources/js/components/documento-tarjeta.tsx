import {
    Eye,
    FileText,
    MoreVertical,
    Pencil,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    puedeEditar,
    urlVencimiento,
    onEliminar,
    renderCargar,
}: Props) {
    const { documento, estado } = ranura;

    // Igual que en la fila: el vencimiento se edita en su lugar, y el menú de
    // acciones ofrece la misma edición para quien la busca ahí.
    const [editandoVencimiento, setEditandoVencimiento] = useState(false);
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
                {/* El vencimiento es el dato por el que se mira esta pantalla,
                    así que nunca se trunca: si falta ancho, se corta el número
                    de documento, que se puede leer en el visor. */}
                <p className="mt-0.5 flex items-baseline gap-1 text-xs text-muted-foreground">
                    {documento?.numero && (
                        <>
                            <span className="truncate font-mono">
                                {documento.numero}
                            </span>
                            <span aria-hidden>·</span>
                        </>
                    )}
                    {/* El vencimiento se corrige acá mismo, de un clic: es el
                        dato que más se carga mal y el que cambia al renovar,
                        y hasta ahora arreglarlo obligaba a volver a subir el
                        archivo escaneado. */}
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
                            className="group/fecha flex w-auto shrink-0 items-center gap-1.5 text-xs tabular-nums"
                            ancho="min-w-32"
                            editando={editandoVencimiento}
                            onEditandoChange={setEditandoVencimiento}
                        >
                            {fecha}
                            <Pencil
                                aria-hidden
                                className="size-3 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/fecha:opacity-100"
                            />
                        </ValorEditable>
                    ) : (
                        <span className="shrink-0 tabular-nums">{fecha}</span>
                    )}
                </p>
            </div>

            {/* El chip solo aparece cuando hay algo que avisar. Un «Vigente»
                en cada fila es ruido —lo normal es que todo esté al día— y
                encima se comía el ancho del número y la fecha, que es lo que
                de verdad se viene a leer acá. */}
            {estado !== 'vigente' && (
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                        tema.chip,
                    )}
                >
                    {ranura.estado_label}
                </span>
            )}

            <div className="flex shrink-0 items-center gap-0.5">
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

                        {/* Eliminar vive en el menú y no como un botón suelto:
                            es destructivo y no hace falta tenerlo a un toque de
                            distancia mientras se revisa el expediente. */}
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
        </div>
    );
}
