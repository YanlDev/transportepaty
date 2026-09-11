import { Cog, FileText, Gauge, Scale, Wrench } from 'lucide-react';
import { Copiable } from '@/components/copiable';
import type { Vehiculo } from '@/types/fleet';

/**
 * Lo técnico: lo que se consulta al comparar unidades o al armar papeles —
 * ejes, motor, VIN y los tres pesos de la tarjeta de propiedad.
 */
export function VehiculoFichaTecnica({
    vehiculo,
    esTracto,
}: {
    vehiculo: Vehiculo;
    esTracto: boolean;
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Gauge className="size-4 text-muted-foreground" />
                    Ficha técnica y pesos
                </h2>
            </div>

            <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                <Tile icono={Cog} label="Ejes">
                    {vehiculo.ejes ? String(vehiculo.ejes) : '—'}
                </Tile>
                {esTracto && (
                    <Tile icono={Wrench} label="N.º de motor">
                        {vehiculo.numero_motor ?? '—'}
                    </Tile>
                )}
                <Tile icono={FileText} label="VIN">
                    {vehiculo.vin ? (
                        <Copiable valor={vehiculo.vin} etiqueta="VIN">
                            <span className="font-mono text-sm">
                                {vehiculo.vin}
                            </span>
                        </Copiable>
                    ) : (
                        '—'
                    )}
                </Tile>
                <Tile icono={Scale} label="Peso neto">
                    {formatearKg(vehiculo.peso_neto)}
                </Tile>
                <Tile icono={Scale} label="Peso bruto">
                    {formatearKg(vehiculo.peso_bruto)}
                </Tile>
                <Tile icono={Scale} label="Carga útil">
                    {formatearKg(vehiculo.carga_util)}
                </Tile>
            </div>
        </section>
    );
}

function Tile({
    icono: Icono,
    label,
    children,
}: {
    icono: typeof Cog;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-border p-3">
            <span
                className="grid size-9 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground"
                aria-hidden
            >
                <Icono className="size-4.5" />
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <div className="truncate text-sm font-medium">{children}</div>
            </div>
        </div>
    );
}

function formatearKg(valor: number | null): string {
    return valor === null ? '—' : `${valor.toLocaleString('es-PE')} kg`;
}
