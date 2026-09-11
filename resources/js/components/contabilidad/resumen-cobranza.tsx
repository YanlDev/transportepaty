import type { ResumenCobranza as Resumen } from '@/types/contabilidad';

/**
 * Lo que el contador viene a ver: cuánto falta cobrar. Va arriba y separado
 * por moneda —sumar soles con dólares da un número que no significa nada— y
 * responde a los filtros, para que «por cobrar de Minsur» sea una lectura y no
 * una cuenta a mano.
 */
export function ResumenCobranza({
    resumen,
    onMes,
}: {
    resumen: Resumen;
    onMes: (mes: string) => void;
}) {
    const cifra = (valor: number) =>
        valor.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

    return (
        <div className="flex flex-wrap items-end gap-x-6 gap-y-3">
            {resumen.montos.map((monto) => (
                <div key={monto.moneda} className="flex items-end gap-4">
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Por cobrar ({monto.moneda})
                        </p>
                        <p className="text-xl font-semibold text-amber-700 tabular-nums dark:text-amber-500">
                            {monto.simbolo} {cifra(monto.por_cobrar)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Cobrado</p>
                        <p className="text-xl font-semibold text-emerald-700 tabular-nums dark:text-emerald-400">
                            {monto.simbolo} {cifra(monto.cobrado)}
                        </p>
                    </div>
                </div>
            ))}

            <PorFacturar meses={resumen.por_facturar} onMes={onMes} />

            {/* Una factura sin monto suma cero y desaparecería del total sin
                que nadie lo note. */}
            {resumen.sin_monto > 0 && (
                <div>
                    <p className="text-xs text-muted-foreground">Sin monto</p>
                    <p className="text-xl font-semibold text-amber-700 tabular-nums dark:text-amber-500">
                        {resumen.sin_monto}{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            {resumen.sin_monto === 1 ? 'factura' : 'facturas'}
                        </span>
                    </p>
                </div>
            )}
        </div>
    );
}

/**
 * Lo que falta facturar, mes por mes. Un solo número global no dice nada útil
 * cuando arrastra medio año: la pregunta real es cuánto de setiembre queda por
 * facturar, porque ese es el trabajo de la semana.
 *
 * Cada mes es un botón que filtra la tabla por él, que es lo único que se hace
 * después de leer el número.
 */
function PorFacturar({
    meses,
    onMes,
}: {
    meses: Resumen['por_facturar'];
    onMes: (mes: string) => void;
}) {
    if (meses.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No queda nada por facturar.
            </p>
        );
    }

    return (
        <div>
            <p className="text-xs text-muted-foreground">Por facturar</p>
            <div className="flex flex-wrap items-end gap-3">
                {meses.map((mes) => (
                    <button
                        key={mes.mes}
                        type="button"
                        onClick={() => onMes(mes.mes)}
                        title={`Filtrar por ${mes.label}`}
                        className="rounded-md px-1 text-left hover:bg-accent"
                    >
                        <span className="text-xl font-semibold tabular-nums">
                            {mes.viajes}
                        </span>{' '}
                        <span className="text-sm text-muted-foreground">
                            {mes.label.replace(/ \d{4}$/, '')}
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );
}
