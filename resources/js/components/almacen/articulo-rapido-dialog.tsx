import { useForm } from '@inertiajs/react';
import { PackagePlus } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { Opcion } from '@/types/almacen';
import type { EnumOption } from '@/types/fleet';

/**
 * Dar de alta un artículo sin salir del conteo o del ingreso: lo mínimo
 * (nombre, unidad, categoría). El resto se completa después en su ficha.
 * Al guardar, la pantalla se recarga con el artículo ya en la lista.
 */
export function ArticuloRapidoDialog({
    categorias,
    ubicaciones,
    unidades,
    ubicacionInicial,
    onCreado,
}: {
    categorias: Opcion[];
    ubicaciones: Opcion[];
    unidades: EnumOption[];
    /** En un conteo, el estante que se está recorriendo. */
    ubicacionInicial?: number | null;
    /** Recibe el id del artículo recién creado, para dejarlo elegido. */
    onCreado?: (id: number) => void;
}) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            nombre: '',
            codigo: '',
            numero_parte: '',
            marca: '',
            categoria_articulo_id: '' as string,
            unidad: 'und',
            unidad_compra: '',
            factor_compra: '',
            ubicacion_almacen_id: ubicacionInicial
                ? String(ubicacionInicial)
                : '',
            volver: true,
        });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        // Va en un portal, pero React propaga el submit por el árbol: sin
        // esto, enviaría también el formulario de la pantalla de atrás.
        evento.stopPropagation();

        post(store().url, {
            preserveScroll: true,
            // La pantalla de atrás (un ingreso a medio llenar) no se pierde.
            preserveState: true,
            onFlash: (flash) => {
                const creado = flash.articulo_creado as
                    | { id: number }
                    | undefined;

                if (creado) {
                    onCreado?.(creado.id);
                }
            },
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(valor) => {
                setAbierto(valor);

                if (!valor) {
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    <PackagePlus className="size-4" />
                    Artículo nuevo
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={guardar}>
                    <DialogHeader>
                        <DialogTitle>Artículo nuevo</DialogTitle>
                        <DialogDescription>
                            Sin código se le pone el siguiente correlativo.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <Field
                                label="Nombre"
                                error={errors.nombre}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={data.nombre}
                                        onChange={(e) =>
                                            setData('nombre', e.target.value)
                                        }
                                        placeholder="Filtro de aceite Scania"
                                    />
                                )}
                            </Field>
                        </div>
                        <Field label="Código" error={errors.codigo}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.codigo}
                                    onChange={(e) =>
                                        setData('codigo', e.target.value)
                                    }
                                    placeholder="Automático"
                                />
                            )}
                        </Field>
                        <Field label="N° de parte" error={errors.numero_parte}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.numero_parte}
                                    onChange={(e) =>
                                        setData('numero_parte', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Unidad" error={errors.unidad} required>
                            {(id) => (
                                <Select
                                    value={data.unidad}
                                    onValueChange={(valor) =>
                                        setData('unidad', valor)
                                    }
                                >
                                    <SelectTrigger id={id}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {unidades.map((unidad) => (
                                            <SelectItem
                                                key={unidad.value}
                                                value={unidad.value}
                                            >
                                                {unidad.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field
                            label="Categoría"
                            error={errors.categoria_articulo_id}
                        >
                            {(id) => (
                                <Select
                                    value={data.categoria_articulo_id}
                                    onValueChange={(valor) =>
                                        setData('categoria_articulo_id', valor)
                                    }
                                >
                                    <SelectTrigger id={id}>
                                        <SelectValue placeholder="Sin categoría" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categorias.map((categoria) => (
                                            <SelectItem
                                                key={categoria.id}
                                                value={String(categoria.id)}
                                            >
                                                {categoria.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field label="Marca" error={errors.marca}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.marca}
                                    onChange={(e) =>
                                        setData('marca', e.target.value)
                                    }
                                    placeholder="Fleetguard, Bosch…"
                                />
                            )}
                        </Field>
                        <Field
                            label="Ubicación"
                            error={errors.ubicacion_almacen_id}
                        >
                            {(id) => (
                                <Select
                                    value={data.ubicacion_almacen_id}
                                    onValueChange={(valor) =>
                                        setData('ubicacion_almacen_id', valor)
                                    }
                                >
                                    <SelectTrigger id={id}>
                                        <SelectValue placeholder="Sin ubicación" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ubicaciones.map((ubicacion) => (
                                            <SelectItem
                                                key={ubicacion.id}
                                                value={String(ubicacion.id)}
                                            >
                                                {ubicacion.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Crear artículo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
