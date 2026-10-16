import { FileText } from 'lucide-react';
import { useState } from 'react';
import { AgregarDocumentoConductorDialog } from '@/components/conductores/agregar-documento-dialog';
import { DocumentoRanuraConductor } from '@/components/conductores/documento-ranura';
import { cn } from '@/lib/utils';
import type { EnumOption, RanuraDocumental } from '@/types/fleet';

type Props = {
    conductorId: number;
    ranuras: RanuraDocumental[];
    tipos: EnumOption[];
    puedeEditar: boolean;
};

type Filtro = 'todos' | 'vigente' | 'por_vencer' | 'vencido' | 'faltante';

const FILTROS: { valor: Filtro; label: string; activo: string }[] = [
    {
        valor: 'todos',
        label: 'Todos',
        activo: 'border-primary bg-primary text-primary-foreground',
    },
    {
        valor: 'vigente',
        label: 'Vigentes',
        activo: 'border-emerald-600 bg-emerald-600 text-white',
    },
    {
        valor: 'por_vencer',
        label: 'Por vencer',
        activo: 'border-amber-500 bg-amber-500 text-amber-950',
    },
    {
        valor: 'vencido',
        label: 'Vencidos',
        activo: 'border-red-600 bg-red-600 text-white',
    },
    {
        valor: 'faltante',
        label: 'Faltan',
        activo: 'border-zinc-500 bg-zinc-500 text-white',
    },
];

/**
 * El expediente del conductor a lo ancho, en cuadrícula. Las pestañas filtran
 * por estado —«qué tiene vencido» es la pregunta de todos los días— y llevan
 * su cuenta, así se sabe cuántos hay antes de tocarlas.
 */
export function ConductorDocumentos({
    conductorId,
    ranuras,
    tipos,
    puedeEditar,
}: Props) {
    const [filtro, setFiltro] = useState<Filtro>('todos');

    // Un opcional sin cargar (el DILER) no cuenta como vigente.
    const estadoDe = (ranura: RanuraDocumental): Filtro | null =>
        ranura.documento === null && ranura.estado === 'vigente'
            ? null
            : ranura.estado;

    const cuenta = (valor: Filtro) =>
        valor === 'todos'
            ? ranuras.length
            : ranuras.filter((ranura) => estadoDe(ranura) === valor).length;

    const visibles =
        filtro === 'todos'
            ? ranuras
            : ranuras.filter((ranura) => estadoDe(ranura) === filtro);

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex flex-col gap-3 border-b p-4">
                <div className="flex items-center justify-between gap-2">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <FileText className="size-4 text-muted-foreground" />
                        Documentos y licencias
                    </h2>
                    {puedeEditar && (
                        <AgregarDocumentoConductorDialog
                            conductorId={conductorId}
                            tipos={tipos}
                        />
                    )}
                </div>

                <div
                    role="tablist"
                    aria-label="Filtrar documentos por estado"
                    className="flex flex-wrap gap-1.5"
                >
                    {FILTROS.map(({ valor, label, activo }) => {
                        const cantidad = cuenta(valor);
                        const elegido = filtro === valor;

                        return (
                            <button
                                key={valor}
                                type="button"
                                role="tab"
                                data-filtro={valor}
                                aria-selected={elegido}
                                onClick={() => setFiltro(valor)}
                                disabled={cantidad === 0 && valor !== 'todos'}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs font-medium tabular-nums transition-colors disabled:opacity-40',
                                    elegido
                                        ? activo
                                        : 'border-border text-muted-foreground hover:bg-accent hover:text-foreground',
                                )}
                            >
                                {label} ({cantidad})
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="grid gap-2 p-4 sm:grid-cols-2 xl:grid-cols-3">
                {visibles.map((ranura) => (
                    <DocumentoRanuraConductor
                        key={ranura.documento?.id ?? ranura.tipo}
                        ranura={ranura}
                        conductorId={conductorId}
                        tipos={tipos}
                        puedeEditar={puedeEditar}
                        compacta
                    />
                ))}
            </div>
        </section>
    );
}
