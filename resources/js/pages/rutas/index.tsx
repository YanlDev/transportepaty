import { Head, setLayoutProps } from '@inertiajs/react';
import { Pencil, Plus, Route } from 'lucide-react';
import { useState } from 'react';
import hub from '@/actions/App/Http/Controllers/ConfiguracionController';
import rutas from '@/actions/App/Http/Controllers/RutaController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { RutaDialog } from '@/components/rutas/ruta-dialog';
import { Button } from '@/components/ui/button';
import { colorTarjeta } from '@/lib/color-tarjeta';
import { colorDeSentido } from '@/lib/etapas';
import { cn } from '@/lib/utils';
import type {
    LineaRuta,
    OpcionesRutas,
    RutaCatalogo,
} from '@/types/abastecimiento';

type Props = {
    rutas: RutaCatalogo[];
    opciones: OpcionesRutas;
};

type Grupo = {
    clave: string;
    titulo: string;
    ayuda: string;
    rutas: RutaCatalogo[];
};

const galones = (valor: number | null) =>
    valor === null
        ? '¿? gal'
        : `${Number.isInteger(valor) ? valor : valor.toFixed(1)} gal`;

/** Las líneas en una frase: «Mina 100 gal · Arequipa 120 gal · viáticos S/ 843». */
function resumen(lineas: LineaRuta[]): string {
    const combustible = lineas
        .filter((linea) => linea.tipo === 'combustible')
        .map(
            (linea) =>
                `${linea.punto ?? linea.concepto} ${galones(linea.galones)}`,
        );
    const viaticos = lineas
        .filter((linea) => linea.tipo !== 'combustible')
        .reduce((suma, linea) => suma + (linea.monto ?? 0), 0);

    return [
        ...combustible,
        viaticos > 0 ? `viáticos S/ ${viaticos.toLocaleString('es-PE')}` : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

function grupos(lista: RutaCatalogo[]): Grupo[] {
    const activas = lista.filter((ruta) => ruta.activa);

    return [
        {
            clave: 'ida',
            titulo: 'Rutas de ida',
            ayuda: 'Lo que se elige al programar la salida desde Juliaca o la mina.',
            rutas: activas.filter(
                (ruta) => ruta.sentido === 'ida' && !ruta.es_complemento,
            ),
        },
        {
            clave: 'regreso',
            titulo: 'Rutas de vuelta',
            ayuda: 'Lo que se elige cuando se sabe con qué regresa la unidad.',
            rutas: activas.filter(
                (ruta) => ruta.sentido === 'regreso' && !ruta.es_complemento,
            ),
        },
        {
            clave: 'complementos',
            titulo: 'Complementos',
            ayuda: 'Se suman a la ruta principal del tramo: un tramo extra o una carga de paso.',
            rutas: activas.filter((ruta) => ruta.es_complemento),
        },
        {
            clave: 'desactivadas',
            titulo: 'Desactivadas',
            ayuda: 'Ya no se ofrecen al programar; los tickets que las tienen las conservan.',
            rutas: lista.filter((ruta) => !ruta.activa),
        },
    ].filter((grupo) => grupo.rutas.length > 0);
}

function FilaRuta({
    ruta,
    zonas,
    onEditar,
}: {
    ruta: RutaCatalogo;
    zonas: OpcionesRutas['zonas'];
    onEditar: () => void;
}) {
    const zona = (valor: string | null) =>
        valor === null
            ? 'destino elegido'
            : (zonas.find((opcion) => opcion.value === valor)?.label ?? valor);
    const recorrido = ruta.paradas
        .map(
            (parada, indice) =>
                `${indice === 0 ? `${parada.lugar_origen || zona(parada.zona_origen)} ` : ''}→ ${parada.lugar_destino || zona(parada.zona_destino)}${parada.tipo_carga === 'vacio' ? ' (vacía)' : ''}`,
        )
        .join(' ');
    const comunes = ruta.lineas.filter((linea) => linea.variante === null);
    const porDefinir = ruta.lineas.some(
        (linea) => linea.tipo === 'combustible' && linea.galones === null,
    );

    return (
        <li className="flex flex-col gap-2 px-4 py-3 md:flex-row md:items-start md:justify-between">
            <div className="flex min-w-0 flex-col gap-1">
                <span className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'rounded px-1.5 py-0.5 text-sm font-semibold',
                            colorTarjeta(ruta.color).cabecera,
                        )}
                    >
                        {ruta.nombre}
                    </span>
                    {ruta.es_complemento && (
                        <span
                            className={cn(
                                'text-xs',
                                ruta.sentido
                                    ? colorDeSentido(ruta.sentido)
                                    : 'text-muted-foreground',
                            )}
                        >
                            {ruta.sentido === 'ida'
                                ? 'en la ida'
                                : ruta.sentido === 'regreso'
                                  ? 'en la vuelta'
                                  : 'en cualquier tramo'}
                        </span>
                    )}
                    {porDefinir && (
                        <span className="rounded border border-dashed border-amber-400 px-1.5 text-[11px] text-amber-700 dark:text-amber-400">
                            galones por definir
                        </span>
                    )}
                </span>
                {(ruta.descripcion || recorrido) && (
                    <span className="text-sm text-muted-foreground">
                        {recorrido || ruta.descripcion}
                    </span>
                )}
                <span className="text-sm">
                    {ruta.variantes.length === 0 ? (
                        resumen(ruta.lineas) || (
                            <span className="text-muted-foreground">
                                Sin dotación propia
                            </span>
                        )
                    ) : (
                        <span className="flex flex-col gap-0.5">
                            {comunes.length > 0 && (
                                <span>Siempre: {resumen(comunes)}</span>
                            )}
                            {ruta.variantes.map((variante, indice) => (
                                <span key={variante.id ?? indice}>
                                    <span className="text-muted-foreground">
                                        {variante.nombre}:
                                    </span>{' '}
                                    {resumen(
                                        ruta.lineas.filter(
                                            (linea) =>
                                                linea.variante === indice,
                                        ),
                                    ) || '—'}
                                </span>
                            ))}
                        </span>
                    )}
                </span>
                {(ruta.dias_viaje !== null || ruta.km_estandar !== null) && (
                    <span className="text-xs text-muted-foreground">
                        {[
                            ruta.dias_viaje !== null &&
                                `${ruta.dias_viaje} días`,
                            ruta.km_estandar !== null &&
                                `${ruta.km_estandar.toLocaleString('es-PE')} km`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                )}
                {ruta.nota && (
                    <span className="text-xs whitespace-pre-line text-muted-foreground">
                        {ruta.nota}
                    </span>
                )}
            </div>
            <Button
                variant="ghost"
                size="sm"
                className="self-start"
                onClick={onEditar}
            >
                <Pencil className="size-3.5" />
                Editar
            </Button>
        </li>
    );
}

/**
 * El catálogo de rutas: cada tramo estándar con su recorrido y su dotación.
 * Es lo que se elige al programar y lo que abastecimiento ejecuta; editar una
 * ruta no cambia los tickets que ya la tienen.
 */
export default function RutasIndex({ rutas: lista, opciones }: Props) {
    const [editando, setEditando] = useState<RutaCatalogo | null | false>(
        false,
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: hub.index().url },
            { title: 'Rutas y dotaciones', href: rutas.index().url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Rutas y dotaciones" />

            <EncabezadoPantalla
                titulo="Rutas y dotaciones"
                descripcion="Cada tramo estándar con su recorrido y lo que se autoriza. Se elige al programar el ticket."
                icono={Route}
                acciones={
                    <Button onClick={() => setEditando(null)}>
                        <Plus className="size-4" />
                        Ruta
                    </Button>
                }
            />

            {grupos(lista).map((grupo) => (
                <section
                    key={grupo.clave}
                    className={cn(
                        'rounded-xl border bg-card',
                        grupo.clave === 'desactivadas' && 'opacity-60',
                    )}
                >
                    <div className="border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">
                            {grupo.titulo}{' '}
                            <span className="font-normal text-muted-foreground tabular-nums">
                                {grupo.rutas.length}
                            </span>
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {grupo.ayuda}
                        </p>
                    </div>
                    <ul className="divide-y">
                        {grupo.rutas.map((ruta) => (
                            <FilaRuta
                                key={ruta.id}
                                ruta={ruta}
                                zonas={opciones.zonas}
                                onEditar={() => setEditando(ruta)}
                            />
                        ))}
                    </ul>
                </section>
            ))}

            {editando !== false && (
                <RutaDialog
                    key={editando?.id ?? 'nueva'}
                    ruta={editando}
                    opciones={opciones}
                    onCerrar={() => setEditando(false)}
                />
            )}
        </div>
    );
}
