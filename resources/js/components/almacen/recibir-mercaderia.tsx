import { useForm } from '@inertiajs/react';
import { PackageCheck } from 'lucide-react';
import { useState } from 'react';
import { recibir } from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { LineaPorRecibir } from '@/types/compras';

/**
 * Llegó la mercadería: se dice cuánto llegó de cada cosa y entra al stock
 * en el acto, con los precios del pedido. El comprobante se completa
 * después, cuando esté a la mano.
 */
export function RecibirMercaderia({
    valeId,
    numero,
    lineas,
    hoy,
    enPartes,
    enDolares = false,
}: {
    valeId: number;
    numero: string;
    lineas: LineaPorRecibir[];
    hoy: string;
    /** El pedido admite recibir menos de lo pedido. */
    enPartes: boolean;
    /** El pedido es en dólares: hace falta el tipo de cambio del día. */
    enDolares?: boolean;
}) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, post, processing, errors, clearErrors, transform } =
        useForm<{
            fecha: string;
            tipo_cambio: string;
            cantidades: Record<number, string>;
        }>({
            fecha: hoy,
            tipo_cambio: '',
            cantidades: Object.fromEntries(
                lineas.map((linea) => [
                    linea.articulo_id,
                    String(linea.pendiente),
                ]),
            ),
        });
    const errores = errors as Record<string, string | undefined>;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();

        transform((datos) => ({
            fecha: datos.fecha,
            tipo_cambio: enDolares ? datos.tipo_cambio : null,
            detalles: lineas
                .map((linea) => ({
                    articulo_id: linea.articulo_id,
                    cantidad: Number(datos.cantidades[linea.articulo_id] || 0),
                }))
                .filter((linea) => linea.cantidad > 0),
        }));
        post(recibir(valeId).url, { preserveScroll: true });
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
                <Button className="h-12 text-base">
                    <PackageCheck className="size-5" />
                    Recibir la mercadería
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Recibir lo del {numero}</DialogTitle>
                        <DialogDescription>
                            Entra al stock ahora, con los precios del pedido. El
                            comprobante se completa después.
                        </DialogDescription>
                    </DialogHeader>

                    <ul className="grid gap-3">
                        {lineas.map((linea) => (
                            <li
                                key={linea.articulo_id}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <span className="flex min-w-0 flex-col">
                                    <span>{linea.descripcion}</span>
                                    <span className="text-xs text-muted-foreground">
                                        Faltan {linea.pendiente} {linea.unidad}
                                    </span>
                                </span>
                                <Input
                                    aria-label={`Cantidad recibida de ${linea.descripcion}`}
                                    inputMode="decimal"
                                    className="h-11 w-24 text-right text-lg tabular-nums"
                                    disabled={!enPartes}
                                    value={
                                        data.cantidades[linea.articulo_id] ?? ''
                                    }
                                    onChange={(evento) =>
                                        setData('cantidades', {
                                            ...data.cantidades,
                                            [linea.articulo_id]:
                                                evento.target.value.replace(
                                                    /[^0-9.]/g,
                                                    '',
                                                ),
                                        })
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                    {enDolares && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="recibir-tipo-cambio">
                                Tipo de cambio del día
                            </Label>
                            <Input
                                id="recibir-tipo-cambio"
                                inputMode="decimal"
                                placeholder="3.750"
                                className="h-11 w-32 tabular-nums"
                                value={data.tipo_cambio}
                                onChange={(evento) =>
                                    setData(
                                        'tipo_cambio',
                                        evento.target.value.replace(
                                            /[^0-9.]/g,
                                            '',
                                        ),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                El pedido es en dólares y el stock se valoriza
                                en soles. Si el comprobante trae otro, se
                                corrige al completarlo.
                            </p>
                            <InputError message={errores.tipo_cambio} />
                        </div>
                    )}
                    {!enPartes && (
                        <p className="text-xs text-muted-foreground">
                            Este pedido es de una sola compra: se recibe todo
                            junto. Si llegó incompleto, gerencia puede activar
                            las compras en partes.
                        </p>
                    )}
                    <InputError
                        message={
                            errores.recepcion ??
                            errores.fecha ??
                            errores.detalles
                        }
                    />

                    <DialogFooter>
                        <Button
                            type="submit"
                            className="h-12"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Sumar al stock
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
