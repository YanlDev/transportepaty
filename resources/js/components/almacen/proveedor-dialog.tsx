import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Almacen/ProveedorController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { Proveedor } from '@/types/almacen';

/**
 * Registrar o corregir un proveedor sin salir de la pantalla: desde el
 * padrón o desde el ingreso, cuando la factura es de alguien nuevo.
 */
export function ProveedorDialog({
    proveedor,
    trigger,
    onCreado,
}: {
    proveedor?: Proveedor;
    trigger?: React.ReactNode;
    /** Recibe el id del proveedor recién creado: la compra lo deja elegido. */
    onCreado?: (id: number) => void;
}) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            ruc: proveedor?.ruc ?? '',
            razon_social: proveedor?.razon_social ?? '',
            nombre_comercial: proveedor?.nombre_comercial ?? '',
            contacto: proveedor?.contacto ?? '',
            telefono: proveedor?.telefono ?? '',
            email: proveedor?.email ?? '',
            direccion: proveedor?.direccion ?? '',
            condicion_pago: proveedor?.condicion_pago ?? '',
            notas: proveedor?.notas ?? '',
            activo: proveedor?.activo ?? true,
        });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        // Va en un portal, pero React propaga el submit por el árbol: sin
        // esto, enviaría también el formulario de la pantalla de atrás.
        evento.stopPropagation();

        const opciones = {
            preserveScroll: true,
            // La pantalla de atrás (un ingreso a medio llenar) no se pierde.
            preserveState: true,
            onFlash: (flash: Record<string, unknown>) => {
                const creado = flash.proveedor_creado as
                    | { id: number }
                    | undefined;

                if (creado) {
                    onCreado?.(creado.id);
                }
            },
            onSuccess: () => {
                if (!proveedor) {
                    reset();
                }

                setAbierto(false);
            },
        };

        if (proveedor) {
            put(update(proveedor.id).url, opciones);
        } else {
            post(store().url, opciones);
        }
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
                {trigger ??
                    (proveedor ? (
                        <Button variant="ghost" size="icon" aria-label="Editar">
                            <Pencil className="size-4" />
                        </Button>
                    ) : (
                        <Button>
                            <Plus className="size-4" />
                            Nuevo proveedor
                        </Button>
                    ))}
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={guardar}>
                    <DialogHeader>
                        <DialogTitle>
                            {proveedor ? 'Editar proveedor' : 'Nuevo proveedor'}
                        </DialogTitle>
                        <DialogDescription>
                            A quién se le compra. El RUC no se repite.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <Field label="RUC" error={errors.ruc}>
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="numeric"
                                    maxLength={11}
                                    value={data.ruc}
                                    onChange={(e) =>
                                        setData('ruc', e.target.value)
                                    }
                                    placeholder="20123456789"
                                />
                            )}
                        </Field>
                        <Field
                            label="Razón social"
                            error={errors.razon_social}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.razon_social}
                                    onChange={(e) =>
                                        setData('razon_social', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            label="Nombre comercial"
                            error={errors.nombre_comercial}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.nombre_comercial}
                                    onChange={(e) =>
                                        setData(
                                            'nombre_comercial',
                                            e.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Contacto" error={errors.contacto}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.contacto}
                                    onChange={(e) =>
                                        setData('contacto', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Teléfono" error={errors.telefono}>
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="tel"
                                    value={data.telefono}
                                    onChange={(e) =>
                                        setData('telefono', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Correo" error={errors.email}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Dirección" error={errors.direccion}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.direccion}
                                    onChange={(e) =>
                                        setData('direccion', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            label="Condición de pago"
                            error={errors.condicion_pago}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.condicion_pago}
                                    onChange={(e) =>
                                        setData(
                                            'condicion_pago',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Contado, crédito 30 días…"
                                />
                            )}
                        </Field>
                        <div className="sm:col-span-2">
                            <Field label="Notas" error={errors.notas}>
                                {(id) => (
                                    <Textarea
                                        id={id}
                                        rows={2}
                                        value={data.notas}
                                        onChange={(e) =>
                                            setData('notas', e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                        {proveedor && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="proveedor-activo"
                                    checked={data.activo}
                                    onCheckedChange={(valor) =>
                                        setData('activo', valor === true)
                                    }
                                />
                                <Label htmlFor="proveedor-activo">
                                    Activo (se puede elegir en las compras)
                                </Label>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
