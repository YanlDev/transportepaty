import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { formatearMonto } from '@/lib/tarifa';
import { cn } from '@/lib/utils';
import type { LineaTarifa, ResultadoTarifa } from '@/types/fleet';

/** Una ruta de la hoja: una columna, con sus días, sus km y su tarifa. */
export type ColumnaHoja = {
    clave: string;
    /** Cliente, destino y material; en la hoja rápida son campos editables. */
    cabecera?: ReactNode;
    /** La celda de días: un campo para tipear o el número ya guardado. */
    dias: ReactNode;
    km: ReactNode;
    /** Null mientras falten días o kilómetros. */
    resultado: ResultadoTarifa | null;
    /**
     * El campo para fijar la tarifa a mano, si la columna lo admite. Sin él,
     * la fila azul muestra lo que se cobra.
     */
    tarifaFinal?: ReactNode;
    /** Acciones al pie de la columna (emitir, quitar). */
    pie?: ReactNode;
    kmNumero: number;
};

/**
 * La hoja de cotización de rutas, con la misma forma que el Excel de la casa:
 * las líneas del tarifario en filas, cada ruta en una columna, días y km en
 * amarillo, y la tarifa al pie.
 *
 * No calcula nada: cada columna trae su resultado, sea el que se acaba de
 * calcular en el navegador o el que quedó guardado en una cotización.
 */
export function HojaTarifa({
    lineas,
    columnas,
    igvPct,
    margen,
}: {
    lineas: LineaTarifa[];
    columnas: ColumnaHoja[];
    igvPct: number;
    /** El porcentaje de margen, o el campo para editarlo. */
    margen: ReactNode;
}) {
    const fijas = lineas
        .map((linea, indice) => ({ linea, indice }))
        .filter(({ linea }) => linea.tipo === 'fijo_dia');
    const variables = lineas
        .map((linea, indice) => ({ linea, indice }))
        .filter(({ linea }) => linea.tipo !== 'fijo_dia');

    const sumaTasas = (grupo: typeof fijas) =>
        grupo.reduce((total, { linea }) => total + linea.tasa, 0);

    const conCabecera = columnas.some((columna) => columna.cabecera);
    const conPie = columnas.some((columna) => columna.pie);
    const rebajaDe = (resultado: ResultadoTarifa | null) =>
        resultado ? resultado.tarifa_calculada - resultado.subtotal : 0;
    // Las filas de la rebaja solo aparecen si alguna ruta cobra otra cosa que
    // la tarifa calculada: sin rebaja serían dos filas repitiendo la azul.
    const conRebaja = columnas.some(
        (columna) => Math.abs(rebajaDe(columna.resultado)) >= 0.005,
    );

    return (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <table className="w-full min-w-max border-collapse text-sm">
                {conCabecera && (
                    <thead>
                        <tr className="border-b border-border align-top">
                            <th className="sticky left-0 z-10 bg-card px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                                Ruta
                            </th>
                            <th className="px-3 py-2" />
                            {columnas.map((columna) => (
                                <th
                                    key={columna.clave}
                                    className="w-44 border-l border-border px-2 py-2 text-left font-normal"
                                >
                                    {columna.cabecera}
                                </th>
                            ))}
                        </tr>
                    </thead>
                )}

                <tbody>
                    <Seccion
                        titulo="Costos fijos"
                        unidad="S/ por día"
                        entrada="Días asignados a la ruta"
                        celdas={columnas.map((columna) => columna.dias)}
                        claves={columnas.map((columna) => columna.clave)}
                    />
                    {fijas.map(({ linea, indice }) => (
                        <Linea
                            key={indice}
                            nombre={linea.nombre}
                            tasa={linea.tasa}
                            importes={columnas.map(
                                (columna) =>
                                    columna.resultado?.desglose.componentes[
                                        indice
                                    ]?.importe ?? null,
                            )}
                        />
                    ))}
                    <Total
                        etiqueta="Total costos fijos"
                        tasa={formatearMonto(sumaTasas(fijas))}
                        valores={columnas.map(
                            (columna) => columna.resultado?.total_fijo ?? null,
                        )}
                    />

                    <Seccion
                        titulo="Costos variables"
                        unidad="S/ por km"
                        entrada="Km recorridos en la ruta"
                        celdas={columnas.map((columna) => columna.km)}
                        claves={columnas.map((columna) => columna.clave)}
                    />
                    {variables.map(({ linea, indice }) => (
                        <Linea
                            key={indice}
                            nombre={linea.nombre}
                            tasa={linea.tasa}
                            importes={columnas.map(
                                (columna) =>
                                    columna.resultado?.desglose.componentes[
                                        indice
                                    ]?.importe ?? null,
                            )}
                        />
                    ))}
                    <Total
                        etiqueta="Total costos variables"
                        tasa={formatearMonto(sumaTasas(variables))}
                        valores={columnas.map(
                            (columna) =>
                                columna.resultado?.total_variable ?? null,
                        )}
                    />

                    <Total
                        etiqueta="Total costo operativo"
                        valores={columnas.map(
                            (columna) =>
                                columna.resultado?.costo_operativo ?? null,
                        )}
                        destacado
                        separado
                    />
                    <Total
                        etiqueta="Margen de operación"
                        tasa={margen}
                        valores={columnas.map((columna) =>
                            columna.resultado
                                ? columna.resultado.tarifa_calculada -
                                  columna.resultado.costo_operativo
                                : null,
                        )}
                    />

                    {conRebaja && (
                        <>
                            <Total
                                etiqueta="Tarifa calculada"
                                valores={columnas.map(
                                    (columna) =>
                                        columna.resultado?.tarifa_calculada ??
                                        null,
                                )}
                                tenue
                            />
                            <Total
                                etiqueta="Rebaja"
                                tasa={
                                    <span className="text-[11px]">
                                        no va en la proforma
                                    </span>
                                }
                                valores={columnas.map((columna) =>
                                    columna.resultado
                                        ? -rebajaDe(columna.resultado)
                                        : null,
                                )}
                                tenue
                            />
                            <Total
                                etiqueta="Margen real"
                                valores={columnas.map(
                                    (columna) =>
                                        columna.resultado?.margen ?? null,
                                )}
                                sufijos={columnas.map((columna) =>
                                    columna.resultado &&
                                    columna.resultado.subtotal > 0
                                        ? `${((columna.resultado.margen / columna.resultado.subtotal) * 100).toFixed(1)} %`
                                        : null,
                                )}
                                tenue
                            />
                        </>
                    )}

                    <tr className="border-t-2 border-primary bg-primary text-primary-foreground">
                        <th
                            scope="row"
                            className="sticky left-0 z-10 bg-primary px-3 py-2.5 text-left font-semibold"
                        >
                            Tarifa de la ruta
                            <span className="block text-xs font-normal opacity-80">
                                sin IGV
                            </span>
                        </th>
                        <td />
                        {columnas.map((columna) => (
                            <td
                                key={columna.clave}
                                className="border-l border-primary-foreground/20 px-3 py-2.5 text-right font-mono text-base font-semibold tabular-nums"
                            >
                                {columna.tarifaFinal ??
                                    (columna.resultado
                                        ? formatearMonto(
                                              columna.resultado.subtotal,
                                          )
                                        : '—')}
                            </td>
                        ))}
                    </tr>

                    <Total
                        etiqueta={`IGV (${(igvPct * 100).toFixed(0)} %)`}
                        valores={columnas.map(
                            (columna) => columna.resultado?.igv ?? null,
                        )}
                        tenue
                    />
                    <Total
                        etiqueta="Total con IGV"
                        valores={columnas.map(
                            (columna) => columna.resultado?.total ?? null,
                        )}
                    />
                    <Total
                        etiqueta="Costo por km"
                        valores={columnas.map((columna) =>
                            columna.resultado && columna.kmNumero > 0
                                ? columna.resultado.costo_operativo /
                                  columna.kmNumero
                                : null,
                        )}
                        tenue
                    />

                    {conPie && (
                        <tr className="border-t border-border">
                            <td className="sticky left-0 z-10 bg-card" />
                            <td />
                            {columnas.map((columna) => (
                                <td
                                    key={columna.clave}
                                    className="border-l border-border px-2 py-2"
                                >
                                    {columna.pie}
                                </td>
                            ))}
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

/**
 * La celda amarilla de días o de km: lo de la ida y, cuando la ruta vuelve sin
 * carga, debajo lo del regreso, que se cobra con las mismas tasas.
 */
export function EntradaRuta({
    etiqueta,
    valor,
    onChange,
    retorno,
    onChangeRetorno,
    decimal = false,
    placeholder,
}: {
    etiqueta: string;
    valor: string;
    onChange: (valor: string) => void;
    /** Undefined cuando la ruta no tiene retorno vacío. */
    retorno?: string;
    onChangeRetorno?: (valor: string) => void;
    /** Días admiten medios; km van enteros. */
    decimal?: boolean;
    placeholder?: string;
}) {
    const clase = 'h-8 bg-background text-right font-mono tabular-nums';

    return (
        <div className="flex flex-col gap-1">
            <Input
                aria-label={etiqueta}
                type="number"
                inputMode={decimal ? 'decimal' : 'numeric'}
                step={decimal ? '0.5' : undefined}
                min={decimal ? 0.5 : 1}
                value={valor}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={clase}
            />
            {retorno !== undefined && onChangeRetorno && (
                <label className="flex flex-col gap-0.5">
                    <span className="text-center text-[11px] leading-tight text-amber-900 dark:text-amber-200">
                        + retorno vacío
                    </span>
                    <Input
                        aria-label={`${etiqueta} del retorno vacío`}
                        type="number"
                        inputMode={decimal ? 'decimal' : 'numeric'}
                        step={decimal ? '0.5' : undefined}
                        min={0}
                        value={retorno}
                        onChange={(e) => onChangeRetorno(e.target.value)}
                        placeholder="0"
                        className={clase}
                    />
                </label>
            )}
        </div>
    );
}

/**
 * La tarifa que se le cobra al cliente, sobre la fila azul. Vacía cobra la
 * calculada, que queda de sugerencia en el placeholder.
 */
export function EntradaTarifa({
    etiqueta,
    valor,
    onChange,
    calculada,
}: {
    etiqueta: string;
    valor: string;
    onChange: (valor: string) => void;
    /** Null mientras la ruta no tenga días y km. */
    calculada: number | null;
}) {
    return (
        <div className="flex flex-col items-end gap-0.5">
            <Input
                aria-label={etiqueta}
                type="number"
                inputMode="decimal"
                step="0.01"
                min={0}
                value={valor}
                onChange={(e) => onChange(e.target.value)}
                placeholder={
                    calculada === null ? '—' : formatearMonto(calculada)
                }
                disabled={calculada === null}
                className="h-8 border-primary-foreground/40 bg-background text-right font-mono text-base font-semibold text-foreground tabular-nums"
            />
            {valor !== '' && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    className="text-[11px] font-normal underline-offset-2 opacity-80 hover:underline"
                >
                    Volver a la calculada
                </button>
            )}
        </div>
    );
}

/** La cabecera de un bloque, con la fila amarilla donde van días o km. */
function Seccion({
    titulo,
    unidad,
    entrada,
    celdas,
    claves,
}: {
    titulo: string;
    unidad: string;
    entrada: string;
    celdas: ReactNode[];
    claves: string[];
}) {
    return (
        <tr className="border-t-2 border-border bg-muted/50">
            <th
                scope="rowgroup"
                className="sticky left-0 z-10 bg-muted px-3 py-2 text-left font-semibold text-foreground"
            >
                {titulo}
            </th>
            <td className="px-3 py-2 text-right text-xs whitespace-nowrap text-muted-foreground">
                {unidad}
            </td>
            {celdas.map((celda, indice) => (
                <td
                    key={claves[indice]}
                    className="border-l border-border bg-amber-100 px-2 py-1.5 dark:bg-amber-500/15"
                >
                    <span className="mb-1 block text-center text-[11px] leading-tight font-medium text-amber-900 dark:text-amber-200">
                        {entrada}
                    </span>
                    {celda}
                </td>
            ))}
        </tr>
    );
}

function Linea({
    nombre,
    tasa,
    importes,
}: {
    nombre: string;
    tasa: number;
    importes: (number | null)[];
}) {
    return (
        <tr className="border-t border-border/60">
            <th
                scope="row"
                className="sticky left-0 z-10 max-w-72 bg-card py-1.5 pr-3 pl-6 text-left font-normal text-foreground"
            >
                {nombre}
            </th>
            <td className="px-3 py-1.5 text-right font-mono text-muted-foreground tabular-nums">
                {formatearMonto(tasa)}
            </td>
            {importes.map((importe, indice) => (
                <td
                    key={indice}
                    className="border-l border-border px-3 py-1.5 text-right font-mono tabular-nums"
                >
                    {importe === null ? '' : formatearMonto(importe)}
                </td>
            ))}
        </tr>
    );
}

function Total({
    etiqueta,
    tasa,
    valores,
    sufijos,
    destacado,
    separado,
    tenue,
}: {
    etiqueta: string;
    tasa?: ReactNode;
    valores: (number | null)[];
    /** Un texto chico junto a cada valor, como el porcentaje del margen. */
    sufijos?: (string | null)[];
    destacado?: boolean;
    separado?: boolean;
    tenue?: boolean;
}) {
    return (
        <tr
            className={cn(
                'border-t border-border',
                separado && 'border-t-2',
                destacado && 'font-semibold',
                tenue && 'text-muted-foreground',
            )}
        >
            <th
                scope="row"
                className={cn(
                    'sticky left-0 z-10 bg-card px-3 py-1.5 text-left',
                    destacado ? 'font-semibold' : 'font-medium',
                )}
            >
                {etiqueta}
            </th>
            <td className="px-3 py-1.5 text-right font-mono text-muted-foreground tabular-nums">
                {tasa}
            </td>
            {valores.map((valor, indice) => (
                <td
                    key={indice}
                    className="border-l border-border px-3 py-1.5 text-right font-mono tabular-nums"
                >
                    {valor === null ? '—' : formatearMonto(valor)}
                    {sufijos?.[indice] && (
                        <span className="block text-[11px] leading-tight">
                            {sufijos[indice]}
                        </span>
                    )}
                </td>
            ))}
        </tr>
    );
}
