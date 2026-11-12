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
    galones_cargado: number | null;
    galones_vacio: number | null;
    nota: string | null;
    /** Las rutas por tramos que lo usan (solo para leer). */
    rutas?: string[];
};

type Props = {
    filas: Fila[];
    zonas: Opcion[];
};

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
export default function GalonesPorTramo({ filas, zonas }: Props) {
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
                tope hasta llenarla.
            </p>

            <div className="overflow-x-auto rounded-xl border bg-card">
                <div className="min-w-[760px]">
                    <div className="grid grid-cols-[1fr_1fr_7rem_7rem_1.4fr_auto] gap-2 border-b px-4 py-2.5 text-xs text-muted-foreground">
                        <span>Desde</span>
                        <span>Hasta</span>
                        <span>Cargado (gal)</span>
                        <span>Vacío (gal)</span>
                        <span>Nota · rutas que lo usan</span>
                        <span className="w-9" />
                    </div>
                    {data.filas.map((fila, indice) => (
                        <div
                            key={fila.id ?? `n${indice}`}
                            className="grid grid-cols-[1fr_1fr_7rem_7rem_1.4fr_auto] items-start gap-2 border-b px-4 py-2.5 last:border-b-0"
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
                            <Input
                                type="number"
                                min={0}
                                step="0.5"
                                inputMode="decimal"
                                aria-label="Galones vacío"
                                placeholder="¿?"
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
