import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';

export type ArticuloPedible = {
    id: number;
    nombre: string;
    codigo: string;
    unidad: string;
    /** Queda stock libre: lo apartado para otras órdenes no cuenta. */
    hay: boolean;
};

/**
 * Pedir un repuesto para una orden: se elige del almacén por su nombre o, si
 * no está en la lista, se escribe con su cantidad y su unidad.
 */
export function FormularioRepuesto({
    url,
    articulos,
    unidades,
    accion = 'Agregar',
    onListo,
    onCancelar,
}: {
    url: string;
    articulos: ArticuloPedible[];
    unidades: EnumOption[];
    accion?: string;
    onListo: () => void;
    onCancelar: () => void;
}) {
    const hayCatalogo = articulos.length > 0;
    const [delAlmacen, setDelAlmacen] = useState(hayCatalogo);
    const { data, setData, post, processing, errors, reset, transform } =
        useForm<{
            articulo_id: number | null;
            descripcion: string;
            cantidad: string;
            unidad: string;
        }>({
            articulo_id: null,
            descripcion: '',
            cantidad: '1',
            unidad: 'und',
        });

    const elegido = articulos.find((a) => a.id === data.articulo_id);

    const pedir = () => {
        transform((datos) =>
            delAlmacen
                ? { articulo_id: datos.articulo_id, cantidad: datos.cantidad }
                : {
                      descripcion: datos.descripcion,
                      cantidad: datos.cantidad,
                      unidad: datos.unidad,
                  },
        );
        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onListo();
            },
        });
    };

    // No es un <form>: se usa dentro del formulario de la orden, y un
    // formulario dentro de otro no es válido.
    return (
        <div className="grid gap-3 rounded-lg border bg-background p-3">
            {hayCatalogo && (
                <div className="grid grid-cols-2 gap-2">
                    {[
                        { valor: true, texto: 'Del almacén' },
                        { valor: false, texto: 'No está en la lista' },
                    ].map((opcion) => (
                        <button
                            key={opcion.texto}
                            type="button"
                            aria-pressed={delAlmacen === opcion.valor}
                            onClick={() => setDelAlmacen(opcion.valor)}
                            className={cn(
                                'h-11 rounded-lg border text-sm font-medium transition-colors',
                                delAlmacen === opcion.valor
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            {opcion.texto}
                        </button>
                    ))}
                </div>
            )}

            {delAlmacen ? (
                <div className="grid gap-1.5">
                    <Label htmlFor="nuevo-repuesto-articulo">Repuesto</Label>
                    <SelectorBuscable
                        id="nuevo-repuesto-articulo"
                        valor={data.articulo_id}
                        onCambio={(valor) => setData('articulo_id', valor)}
                        etiqueta="Repuesto"
                        opciones={articulos.map((articulo) => ({
                            valor: articulo.id,
                            etiqueta: articulo.nombre,
                            detalle: `${articulo.codigo} ${articulo.hay ? '' : 'sin stock'}`,
                        }))}
                        invalido={!!errors.articulo_id}
                    />
                    {elegido && (
                        <p
                            className={cn(
                                'text-xs',
                                elegido.hay
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {elegido.hay
                                ? 'Hay en el almacén.'
                                : 'No hay stock libre: se tendrá que comprar.'}
                        </p>
                    )}
                    <InputError
                        message={errors.articulo_id ?? errors.descripcion}
                    />
                </div>
            ) : (
                <div className="grid gap-1.5">
                    <Label htmlFor="nuevo-repuesto-descripcion">
                        ¿Qué repuesto es?
                    </Label>
                    <Input
                        id="nuevo-repuesto-descripcion"
                        className="h-11"
                        value={data.descripcion}
                        onChange={(evento) =>
                            setData('descripcion', evento.target.value)
                        }
                        placeholder="Nombre, medida, marca"
                    />
                    <InputError message={errors.descripcion} />
                </div>
            )}

            <div className="grid grid-cols-2 gap-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="nuevo-repuesto-cantidad">Cantidad</Label>
                    <Input
                        id="nuevo-repuesto-cantidad"
                        inputMode="decimal"
                        className="h-11 tabular-nums"
                        value={data.cantidad}
                        onChange={(evento) =>
                            setData(
                                'cantidad',
                                evento.target.value.replace(/[^0-9.]/g, ''),
                            )
                        }
                    />
                    <InputError message={errors.cantidad} />
                </div>
                {!delAlmacen && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="nuevo-repuesto-unidad">Unidad</Label>
                        <select
                            id="nuevo-repuesto-unidad"
                            value={data.unidad}
                            onChange={(evento) =>
                                setData('unidad', evento.target.value)
                            }
                            className="h-11 rounded-md border bg-background px-3 text-sm"
                        >
                            {unidades.map((unidad) => (
                                <option key={unidad.value} value={unidad.value}>
                                    {unidad.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
            </div>

            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="h-11"
                    onClick={onCancelar}
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    className="h-11 flex-1"
                    onClick={pedir}
                    disabled={
                        processing ||
                        (delAlmacen
                            ? data.articulo_id === null
                            : data.descripcion.trim() === '')
                    }
                >
                    {processing && <Spinner />}
                    {accion}
                </Button>
            </div>
        </div>
    );
}
