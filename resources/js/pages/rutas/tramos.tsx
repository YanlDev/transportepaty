import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { Fuel, House, Plus, X } from 'lucide-react';
import hub from '@/actions/App/Http/Controllers/ConfiguracionController';
import tramosRutas from '@/actions/App/Http/Controllers/GalonesPorTramoController';
import rutas from '@/actions/App/Http/Controllers/RutaController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Opcion } from '@/types/vueltas';

type Fila = {
    id?: number;
    zona_origen: string;
    zona_destino: string;
    /** Sin carga vale para cualquiera; con carga manda solo para esa. */
    tipo_carga: string | null;
    galones_cargado: number | null;
    galones_vacio: number | null;
    /** Cargado según el peso: con bandas, el número único no se usa. */
    bandas?: Banda[];
    nota: string | null;
    /** Las rutas por tramos que lo usan (solo para leer). */
    rutas?: string[];
};

type Banda = {
    peso_desde: number | null;
    peso_hasta: number | null;
    galones: number | null;
};

/** Cada banda empieza donde termina la anterior: solo se escribe «hasta». */
const encadenar = (bandas: Banda[]): Banda[] =>
    bandas.map((banda, indice) => ({
        ...banda,
        peso_desde: indice === 0 ? null : bandas[indice - 1].peso_hasta,
    }));

type Props = {
    filas: Fila[];
    zonas: Opcion[];
    cargas: Opcion[];
};

const CUALQUIER_CARGA = 'cualquiera';

const numeroONulo = (valor: string): number | null =>
    valor === '' ? null : Number(valor);

function Zona({
    valor,
    zonas,
    placeholder,
    invalido,
    onCambiar,
}: {
    valor: string;
    zonas: Opcion[];
    placeholder: string;
    invalido?: boolean;
    onCambiar: (valor: string) => void;
}) {
    return (
        <Select value={valor || undefined} onValueChange={onCambiar}>
            <SelectTrigger
                className="w-full min-w-0"
                aria-label={placeholder}
                aria-invalid={invalido || undefined}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {zonas.map((zona) => (
                    <SelectItem key={zona.value} value={zona.value}>
                        {zona.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Galones por tramo: cuánto gasta la unidad de una zona a otra, cargada y
 * vacía. Juliaca es la base: un viaje particular es hasta el destino cargado
 * y de ahí a Juliaca vacío (o cargado). Las rutas por tramos suman de aquí.
 */
export default function GalonesPorTramo({ filas, zonas, cargas }: Props) {
    const { data, setData, put, processing, errors, isDirty } = useForm<{
        filas: Fila[];
    }>({ filas });
    const errores = errors as Record<string, string | undefined>;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: hub.index().url },
            { title: 'Rutas y dotaciones', href: rutas.index().url },
            { title: 'Galones por tramo', href: tramosRutas.index().url },
        ],
    });

    const cambiar = (indice: number, cambios: Partial<Fila>) =>
        setData(
            'filas',
            data.filas.map((fila, i) =>
                i === indice ? { ...fila, ...cambios } : fila,
            ),
        );

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        put(tramosRutas.guardar().url, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={guardar}
            className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6"
        >
            <Head title="Galones por tramo" />

            <EncabezadoPantalla
                titulo="Galones por tramo"
                descripcion="Cuánto gasta la unidad de una zona a otra, cargada y vacía. Las rutas por tramos suman de aquí, y sus tickets también."
                icono={Fuel}
                acciones={
                    <Button type="submit" disabled={processing || !isDirty}>
                        Guardar
                    </Button>
                }
            />

            <p className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                <House className="size-4 shrink-0" />
                Juliaca es la base: un viaje particular es hasta el destino
                cargado, y de ahí a Juliaca vacío (o cargado si trae algo). Una
                casilla vacía queda «por definir»: la ruta que la use no tiene
                tope hasta llenarla. Un tramo con carga (Lima → Mina ·
                Particular) manda sobre el general solo cuando va cargado con
                eso.
            </p>

            <div className="overflow-x-auto rounded-xl border bg-card">
                <div className="min-w-[940px]">
                    <div className="grid grid-cols-[1fr_1fr_11rem_7rem_7rem_1.4fr_auto] gap-2 border-b px-4 py-2.5 text-xs text-muted-foreground">
                        <span>Desde</span>
                        <span>Hasta</span>
                        <span>Carga</span>
                        <span>Cargado (gal)</span>
                        <span>Vacío (gal)</span>
                        <span>Nota · rutas que lo usan</span>
                        <span className="w-9" />
                    </div>
                    {data.filas.map((fila, indice) => (
                        <div
                            key={fila.id ?? `n${indice}`}
                            className="grid grid-cols-[1fr_1fr_11rem_7rem_7rem_1.4fr_auto] items-start gap-2 border-b px-4 py-2.5 last:border-b-0"
                        >
                            <Zona
                                valor={fila.zona_origen}
                                zonas={zonas}
                                placeholder="Desde"
                                invalido={Boolean(
                                    errores[`filas.${indice}.zona_origen`],
                                )}
                                onCambiar={(valor) =>
                                    cambiar(indice, { zona_origen: valor })
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <Zona
                                    valor={fila.zona_destino}
                                    zonas={zonas}
                                    placeholder="Hasta"
                                    invalido={Boolean(
                                        errores[`filas.${indice}.zona_destino`],
                                    )}
                                    onCambiar={(valor) =>
                                        cambiar(indice, { zona_destino: valor })
                                    }
                                />
                                {errores[`filas.${indice}.zona_destino`] && (
                                    <span className="text-xs text-destructive">
                                        {
                                            errores[
                                                `filas.${indice}.zona_destino`
                                            ]
                                        }
                                    </span>
                                )}
                            </div>
                            <Select
                                value={fila.tipo_carga ?? CUALQUIER_CARGA}
                                onValueChange={(valor) =>
                                    cambiar(
                                        indice,
                                        valor === CUALQUIER_CARGA
                                            ? { tipo_carga: null }
                                            : {
                                                  tipo_carga: valor,
                                                  galones_vacio: null,
                                              },
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-full min-w-0"
                                    aria-label="Carga"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={CUALQUIER_CARGA}>
                                        Cualquier carga
                                    </SelectItem>
                                    {cargas.map((carga) => (
                                        <SelectItem
                                            key={carga.value}
                                            value={carga.value}
                                        >
                                            {carga.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <div className="flex flex-col gap-1">
                                {(fila.bandas ?? []).length > 0 ? (
                                    <span className="flex h-9 items-center text-sm text-muted-foreground">
                                        según el peso
                                    </span>
                                ) : (
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.5"
                                        inputMode="decimal"
                                        aria-label="Galones cargado"
                                        placeholder="¿?"
                                        value={fila.galones_cargado ?? ''}
                                        onChange={(evento) =>
                                            cambiar(indice, {
                                                galones_cargado: numeroONulo(
                                                    evento.target.value,
                                                ),
                                            })
                                        }
                                    />
                                )}
                                <button
                                    type="button"
                                    className="self-start text-xs font-medium text-primary hover:underline"
                                    onClick={() =>
                                        cambiar(
                                            indice,
                                            (fila.bandas ?? []).length > 0
                                                ? { bandas: [] }
                                                : {
                                                      galones_cargado: null,
                                                      bandas: [
                                                          {
                                                              peso_desde: null,
                                                              peso_hasta: null,
                                                              galones:
                                                                  fila.galones_cargado,
                                                          },
                                                      ],
                                                  },
                                        )
                                    }
                                >
                                    {(fila.bandas ?? []).length > 0
                                        ? 'Un solo número'
                                        : 'Según el peso'}
                                </button>
                            </div>
                            <Input
                                type="number"
                                min={0}
                                step="0.5"
                                inputMode="decimal"
                                aria-label="Galones vacío"
                                // Vacío no lleva carga: lo da el tramo general.
                                disabled={fila.tipo_carga !== null}
                                title={
                                    fila.tipo_carga !== null
                                        ? 'Vacío lo da el tramo de cualquier carga'
                                        : undefined
                                }
                                placeholder={
                                    fila.tipo_carga !== null ? '—' : '¿?'
                                }
                                value={fila.galones_vacio ?? ''}
                                onChange={(evento) =>
                                    cambiar(indice, {
                                        galones_vacio: numeroONulo(
                                            evento.target.value,
                                        ),
                                    })
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <Input
                                    aria-label="Nota"
                                    placeholder="Nota (opcional)"
                                    value={fila.nota ?? ''}
                                    onChange={(evento) =>
                                        cambiar(indice, {
                                            nota: evento.target.value,
                                        })
                                    }
                                />
                                {(fila.rutas ?? []).length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        En: {(fila.rutas ?? []).join(', ')}
                                    </span>
                                )}
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar tramo"
                                onClick={() =>
                                    setData(
                                        'filas',
                                        data.filas.filter(
                                            (_, i) => i !== indice,
                                        ),
                                    )
                                }
                            >
                                <X className="size-4" />
                            </Button>
                            {(fila.bandas ?? []).length > 0 && (
                                <BandasDePeso
                                    bandas={fila.bandas ?? []}
                                    error={errores[`filas.${indice}.bandas`]}
                                    onCambiar={(bandas) =>
                                        cambiar(indice, { bandas })
                                    }
                                />
                            )}
                        </div>
                    ))}
                    {data.filas.length === 0 && (
                        <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                            Todavía no hay tramos.
                        </p>
                    )}
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                className="self-start"
                onClick={() =>
                    setData('filas', [
                        ...data.filas,
                        {
                            zona_origen: '',
                            zona_destino: 'juliaca',
                            tipo_carga: null,
                            galones_cargado: null,
                            galones_vacio: null,
                            nota: null,
                        },
                    ])
                }
            >
                <Plus className="size-4" />
                Tramo
            </Button>
        </form>
    );
}

/**
 * Lo que gasta cargado según las toneladas: «hasta 5 t: 110 gal», «hasta
 * 8 t: 120 gal»… La última puede quedar sin «hasta»: de ahí para arriba.
 */
function BandasDePeso({
    bandas,
    error,
    onCambiar,
}: {
    bandas: Banda[];
    error?: string;
    onCambiar: (bandas: Banda[]) => void;
}) {
    const cambiar = (indice: number, cambios: Partial<Banda>) =>
        onCambiar(
            encadenar(
                bandas.map((banda, i) =>
                    i === indice ? { ...banda, ...cambios } : banda,
                ),
            ),
        );

    return (
        <div className="col-span-full flex flex-col gap-2 rounded-lg bg-muted/50 px-3 py-2.5">
            <span className="text-xs font-medium">
                Cargado, según las toneladas de la GR
            </span>
            <div className="flex flex-wrap gap-2">
                {bandas.map((banda, indice) => {
                    const ultima = indice === bandas.length - 1;

                    return (
                        <div
                            key={indice}
                            className="flex items-center gap-1.5 rounded-md border bg-background px-2 py-1.5 text-sm"
                        >
                            <span className="text-muted-foreground">
                                {ultima && banda.peso_hasta === null
                                    ? banda.peso_desde === null
                                        ? 'cualquier peso'
                                        : `más de ${banda.peso_desde} t, o hasta`
                                    : 'hasta'}
                            </span>
                            <Input
                                type="number"
                                min={0}
                                step="0.5"
                                inputMode="decimal"
                                className="h-8 w-16"
                                aria-label="Hasta cuántas toneladas"
                                placeholder={ultima ? '—' : 't'}
                                value={banda.peso_hasta ?? ''}
                                onChange={(evento) =>
                                    cambiar(indice, {
                                        peso_hasta: numeroONulo(
                                            evento.target.value,
                                        ),
                                    })
                                }
                            />
                            <span className="text-muted-foreground">t:</span>
                            <Input
                                type="number"
                                min={0}
                                step="0.5"
                                inputMode="decimal"
                                className="h-8 w-20"
                                aria-label="Galones de la banda"
                                placeholder="¿?"
                                value={banda.galones ?? ''}
                                onChange={(evento) =>
                                    cambiar(indice, {
                                        galones: numeroONulo(
                                            evento.target.value,
                                        ),
                                    })
                                }
                            />
                            <span className="text-muted-foreground">gal</span>
                            {bandas.length > 1 && (
                                <button
                                    type="button"
                                    aria-label="Quitar banda"
                                    className="text-muted-foreground hover:text-foreground"
                                    onClick={() =>
                                        onCambiar(
                                            encadenar(
                                                bandas.filter(
                                                    (_, i) => i !== indice,
                                                ),
                                            ),
                                        )
                                    }
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>
                    );
                })}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        onCambiar(
                            encadenar([
                                ...bandas,
                                {
                                    peso_desde: null,
                                    peso_hasta: null,
                                    galones: null,
                                },
                            ]),
                        )
                    }
                >
                    <Plus className="size-4" />
                    Banda
                </Button>
            </div>
            <span className="text-xs text-muted-foreground">
                Cada banda empieza donde termina la anterior. Deja la última sin
                «hasta» para todo lo que pese más.
            </span>
            {error && <span className="text-xs text-destructive">{error}</span>}
        </div>
    );
}
