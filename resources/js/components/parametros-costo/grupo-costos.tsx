import { ComponenteCard } from '@/components/parametros-costo/componente-card';
import { formatearSoles } from '@/lib/format';
import type { ComponenteCosto, EnumOption } from '@/types/fleet';

export function GrupoCostos({
    titulo,
    descripcion,
    componentes,
    naturalezas,
}: {
    titulo: string;
    descripcion: string;
    componentes: ComponenteCosto[];
    naturalezas: EnumOption[];
}) {
    if (componentes.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-3">
            <div>
                <h2 className="text-sm font-semibold text-foreground">
                    {titulo}
                </h2>
                <p className="text-xs text-muted-foreground">{descripcion}</p>
            </div>

            {componentes.map((componente) => (
                <ComponenteCard
                    key={componente.id}
                    componente={componente}
                    naturalezas={naturalezas}
                />
            ))}
        </section>
    );
}

export function ResumenCosto({
    titulo,
    unidad,
    total,
    directo,
    indirecto,
}: {
    titulo: string;
    unidad: string;
    total: number;
    directo: number;
    indirecto: number;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <p className="text-xs text-muted-foreground">
                {titulo} {unidad}
            </p>
            <p className="font-mono text-2xl font-semibold text-foreground tabular-nums">
                {formatearSoles(total)}
            </p>
            <div className="mt-2 flex gap-4 text-xs text-muted-foreground">
                <span>Directo {formatearSoles(directo)}</span>
                <span>Indirecto {formatearSoles(indirecto)}</span>
            </div>
        </div>
    );
}
