import { Eye, Trash2, Upload } from 'lucide-react';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { formatearFecha } from '@/lib/format';
import type { EstadoDocumento, RanuraDocumental } from '@/types/fleet';

/**
 * Los problemas gritan y lo que está en regla se calla: un documento vigente no
 * necesita mirarse, así que va en neutro; lo que falta o venció tiñe la tarjeta
 * entera. La elevación es igual para todos —lo que cambia es el color— para que
 * la lista se lea como una pila de fichas y no como una tabla.
 */
const estilo: Record<
    EstadoDocumento,
    { tarjeta: string; barra: string; chip: string }
> = {
    vigente: {
        tarjeta:
            'border-border bg-card hover:border-zinc-300 dark:hover:border-zinc-700',
        barra: 'bg-emerald-500',
        chip: 'bg-muted text-muted-foreground',
    },
    por_vencer: {
        tarjeta:
            'border-amber-300 bg-amber-50/60 hover:border-amber-400 dark:border-amber-900 dark:bg-amber-950/25',
        barra: 'bg-amber-500',
        chip: 'bg-amber-500 text-amber-950',
    },
    vencido: {
        tarjeta:
            'border-red-300 bg-red-50/70 hover:border-red-400 dark:border-red-900 dark:bg-red-950/30',
        barra: 'bg-red-500',
        chip: 'bg-red-600 text-white',
    },
    faltante: {
        tarjeta:
            'border-dashed border-zinc-300 bg-transparent hover:border-red-400 dark:border-zinc-700',
        barra: 'bg-zinc-300 dark:bg-zinc-700',
        chip: 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
    },
};

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
    const tema = estilo[estado];

    const fecha = documento?.fecha_vencimiento
        ? `${estado === 'vencido' ? 'Venció' : 'Vence'} ${formatearFecha(documento.fecha_vencimiento)}`
        : documento
          ? 'Sin vencimiento'
          : 'No cargado';

    return (
        <div
            className={`group/doc flex items-stretch gap-0 border shadow-sm transition-all hover:shadow-md ${tema.tarjeta}`}
        >
            <span className={`w-1.5 shrink-0 ${tema.barra}`} />

            <div className="flex min-w-0 flex-1 items-center gap-3 py-3 pr-2 pl-3.5">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm leading-tight font-semibold text-foreground">
                        {ranura.label}
                    </p>
                    <p className="mt-1 truncate text-xs text-muted-foreground">
                        {documento?.numero && (
                            <span className="font-mono">
                                {documento.numero} ·{' '}
                            </span>
                        )}
                        <span className="tabular-nums">{fecha}</span>
                    </p>
                </div>

                <span
                    className={`shrink-0 px-2 py-1 text-[10px] font-bold tracking-wider uppercase ${tema.chip}`}
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
                                  className="grid size-8 place-items-center text-muted-foreground transition-colors hover:bg-navy-800 hover:text-white"
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
                                        className="grid size-8 place-items-center text-muted-foreground transition-colors hover:bg-navy-800 hover:text-white"
                                    >
                                        <Eye className="size-4" />
                                    </button>
                                }
                            />

                            {puedeGestionar && (
                                <button
                                    type="button"
                                    onClick={onEliminar}
                                    title={`Eliminar ${ranura.label}`}
                                    aria-label={`Eliminar ${ranura.label}`}
                                    className="grid size-8 place-items-center text-muted-foreground opacity-0 transition-all group-hover/doc:opacity-100 hover:bg-destructive hover:text-white focus-visible:opacity-100"
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
