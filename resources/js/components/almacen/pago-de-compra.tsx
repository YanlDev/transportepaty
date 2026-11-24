import { useForm } from '@inertiajs/react';
import { Banknote, CircleCheck, FileText, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    anular,
    store,
} from '@/actions/App/Http/Controllers/Almacen/PagoCompraController';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { CampoArchivo } from '@/components/campo-archivo';
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
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PagoDelPedido } from '@/types/compras';
import type { EnumOption } from '@/types/fleet';

type Props = {
    valeId: number;
    numero: string;
    simbolo: string;
    formaPago: string;
    pago: PagoDelPedido;
    puedePagar: boolean;
    medios: EnumOption[];
    cuentas: { id: number; nombre: string }[];
    hoy: string;
};

/**
 * El pago de un pedido de compra aprobado: cuánto se debe, hasta cuándo y a
 * qué cuenta, y los pagos ya anotados con su voucher. Transpaty no mueve el
 * dinero: quien hizo la transferencia la anota acá con «Ya pagué».
 */
export function PagoDeCompra({
    valeId,
    numero,
    simbolo,
    formaPago,
    pago,
    puedePagar,
    medios,
    cuentas,
    hoy,
}: Props) {
    const monto = (valor: number) =>
        `${simbolo} ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const pagado = pago.por_pagar <= 0.005;

    return (
        <section
            className={cn(
                'flex flex-col gap-4 rounded-xl border p-5',
                pagado
                    ? 'border-emerald-500/40 bg-emerald-500/5'
                    : pago.vencido
                      ? 'border-red-500/40 bg-red-500/5'
                      : 'border-sky-500/40 bg-sky-500/5',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        {pagado ? (
                            <CircleCheck className="size-4 text-emerald-600" />
                        ) : (
                            <Banknote className="size-4" />
                        )}
                        {pagado ? 'Pagado al proveedor' : 'Pago pendiente'}
                    </h2>
                    {!pagado && (
                        <>
                            <p className="text-2xl font-semibold tabular-nums">
                                {monto(pago.por_pagar)}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {formaPago}
                                {pago.vence_el &&
                                    ` · ${pago.vencido ? 'venció el' : 'hasta el'} ${formatearFecha(pago.vence_el)}`}
                                {pago.pagado > 0 &&
                                    ` · ya se pagaron ${monto(pago.pagado)}`}
                            </p>
                        </>
                    )}
                    {pago.cuenta_proveedor ? (
                        <p className="text-sm">
                            <span className="text-muted-foreground">
                                Se le paga a:{' '}
                            </span>
                            <span className="font-medium">
                                {pago.cuenta_proveedor}
                            </span>
                        </p>
                    ) : (
                        !pagado && (
                            <p className="text-xs text-muted-foreground">
                                El proveedor no tiene cuenta anotada: se puede
                                agregar en Proveedores.
                            </p>
                        )
                    )}
                </div>
                {puedePagar && !pagado && (
                    <RegistrarPago
                        valeId={valeId}
                        numero={numero}
                        simbolo={simbolo}
                        porPagar={pago.por_pagar}
                        medios={medios}
                        cuentas={cuentas}
                        hoy={hoy}
                    />
                )}
            </div>

            {pago.pagos.length > 0 && (
                <ul className="divide-y rounded-lg border bg-background text-sm">
                    {pago.pagos.map((anotado) => (
                        <li
                            key={anotado.id}
                            className={cn(
                                'flex flex-wrap items-center justify-between gap-3 p-3',
                                anotado.anulado && 'opacity-60',
                            )}
                        >
                            <span className="flex min-w-0 flex-col">
                                <span
                                    className={cn(
                                        'font-medium tabular-nums',
                                        anotado.anulado && 'line-through',
                                    )}
                                >
                                    {monto(anotado.monto)} ·{' '}
                                    {formatearFecha(anotado.fecha)}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {anotado.medio}
                                    {anotado.cuenta && ` · ${anotado.cuenta}`}
                                    {anotado.numero_operacion &&
                                        ` · op. ${anotado.numero_operacion}`}
                                    {anotado.registro &&
                                        ` · anotó ${anotado.registro}`}
                                </span>
                                {anotado.anulado && (
                                    <span className="text-xs text-red-700 dark:text-red-400">
                                        Anulado: {anotado.motivo_anulacion}
                                    </span>
                                )}
                            </span>
                            <span className="flex items-center gap-2">
                                {anotado.voucher_url && (
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={anotado.voucher_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <FileText className="size-4" />
                                            Voucher
                                        </a>
                                    </Button>
                                )}
                                {puedePagar && !anotado.anulado && (
                                    <AnularDialog
                                        url={
                                            anular({
                                                vale: valeId,
                                                pago: anotado.id,
                                            }).url
                                        }
                                        titulo="¿Anular este pago?"
                                        descripcion="No se borra: queda a la vista con el motivo, y el monto vuelve a figurar por pagar."
                                    />
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {pago.pagado > 0 && (
                <p className="text-xs text-muted-foreground">
                    Con pagos anotados el pedido no vence, y no se puede
                    corregir ni anular: primero hay que anular el pago.
                </p>
            )}
        </section>
    );
}

type Operacion = {
    monto: string;
    numero_operacion: string;
    voucher: File | null;
};

const soloMonto = (texto: string) => texto.replace(/[^0-9.]/g, '');

function RegistrarPago({
    valeId,
    numero,
    simbolo,
    porPagar,
    medios,
    cuentas,
    hoy,
}: {
    valeId: number;
    numero: string;
    simbolo: string;
    porPagar: number;
    medios: EnumOption[];
    cuentas: { id: number; nombre: string }[];
    hoy: string;
}) {
    const [abierto, setAbierto] = useState(false);
    const [preparando, setPreparando] = useState(false);
    const {
        data,
        setData,
        post,
        transform,
        processing,
        progress,
        errors,
        reset,
        clearErrors,
    } = useForm<{
        fecha: string;
        medio: string;
        cuenta_bancaria_id: string;
        operaciones: Operacion[];
    }>({
        fecha: hoy,
        medio: 'transferencia',
        cuenta_bancaria_id: '',
        operaciones: [
            { monto: porPagar.toFixed(2), numero_operacion: '', voucher: null },
        ],
    });
    const efectivo = data.medio === 'efectivo';
    // El efectivo no tiene operaciones: es un solo monto.
    const operaciones = efectivo
        ? data.operaciones.slice(0, 1)
        : data.operaciones;
    const varias = operaciones.length > 1;
    const suma = operaciones.reduce(
        (total, operacion) => total + (Number(operacion.monto) || 0),
        0,
    );
    const sePasa = suma - porPagar > 0.005;
    const fallos = errors as Record<string, string | undefined>;
    const dinero = (valor: number) =>
        `${simbolo} ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const cambiar = (indice: number, cambio: Partial<Operacion>) =>
        setData(
            'operaciones',
            data.operaciones.map((operacion, posicion) =>
                posicion === indice ? { ...operacion, ...cambio } : operacion,
            ),
        );

    const agregar = () =>
        setData('operaciones', [
            ...data.operaciones,
            {
                // La nueva operación arranca con lo que falta para completar.
                monto: Math.max(porPagar - suma, 0).toFixed(2),
                numero_operacion: '',
                voucher: null,
            },
        ]);

    const quitar = (indice: number) =>
        setData(
            'operaciones',
            data.operaciones.filter((_, posicion) => posicion !== indice),
        );

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();

        transform((datos) => ({
            ...datos,
            operaciones: efectivo
                ? [{ monto: datos.operaciones[0].monto }]
                : datos.operaciones,
        }));

        post(store(valeId).url, {
            preserveScroll: true,
            // Sin voucher (efectivo) viaja como un formulario normal.
            forceFormData: operaciones.some(
                (operacion) => operacion.voucher !== null,
            ),
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
                <Button className="h-12 text-base">
                    <CircleCheck className="size-5" />
                    Ya pagué
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Anotar el pago del {numero}</DialogTitle>
                        <DialogDescription>
                            La transferencia se hace en el banco, como siempre.
                            Acá solo queda anotada, con su voucher. Si se pagó
                            en varias operaciones, agrega una por cada voucher.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5">
                        <Label>¿Cómo se pagó?</Label>
                        <div className="flex flex-wrap gap-2">
                            {medios.map((medio) => (
                                <button
                                    key={medio.value}
                                    type="button"
                                    aria-pressed={data.medio === medio.value}
                                    onClick={() =>
                                        setData('medio', medio.value)
                                    }
                                    className={cn(
                                        'min-h-11 rounded-full border px-4 text-sm font-medium transition-colors',
                                        data.medio === medio.value
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {medio.label}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.medio} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="pago-fecha">Fecha</Label>
                            <Input
                                id="pago-fecha"
                                type="date"
                                className="h-11"
                                max={hoy}
                                value={data.fecha}
                                onChange={(evento) =>
                                    setData('fecha', evento.target.value)
                                }
                            />
                            <InputError message={errors.fecha} />
                        </div>
                        {!efectivo && cuentas.length > 0 && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="pago-cuenta">
                                    Cuenta de salida (opcional)
                                </Label>
                                <select
                                    id="pago-cuenta"
                                    value={data.cuenta_bancaria_id}
                                    onChange={(evento) =>
                                        setData(
                                            'cuenta_bancaria_id',
                                            evento.target.value,
                                        )
                                    }
                                    className="h-11 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Sin indicar</option>
                                    {cuentas.map((cuenta) => (
                                        <option
                                            key={cuenta.id}
                                            value={cuenta.id}
                                        >
                                            {cuenta.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {operaciones.map((operacion, indice) => (
                        <fieldset
                            key={indice}
                            className={cn(
                                'flex flex-col gap-3',
                                varias && 'rounded-lg border p-3',
                            )}
                        >
                            {varias && (
                                <div className="flex items-center justify-between">
                                    <legend className="text-sm font-semibold">
                                        Operación {indice + 1}
                                    </legend>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => quitar(indice)}
                                    >
                                        <Trash2 className="size-4" />
                                        Quitar
                                    </Button>
                                </div>
                            )}
                            <div
                                className={cn(
                                    'grid gap-3',
                                    !efectivo && 'grid-cols-2',
                                )}
                            >
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`pago-monto-${indice}`}>
                                        Monto ({simbolo})
                                    </Label>
                                    <Input
                                        id={`pago-monto-${indice}`}
                                        inputMode="decimal"
                                        className="h-11 text-lg tabular-nums"
                                        value={operacion.monto}
                                        onChange={(evento) =>
                                            cambiar(indice, {
                                                monto: soloMonto(
                                                    evento.target.value,
                                                ),
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            fallos[
                                                `operaciones.${indice}.monto`
                                            ]
                                        }
                                    />
                                </div>
                                {!efectivo && (
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`pago-operacion-${indice}`}
                                        >
                                            N.º de operación
                                        </Label>
                                        <Input
                                            id={`pago-operacion-${indice}`}
                                            className="h-11 tabular-nums"
                                            value={operacion.numero_operacion}
                                            onChange={(evento) =>
                                                cambiar(indice, {
                                                    numero_operacion:
                                                        evento.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                fallos[
                                                    `operaciones.${indice}.numero_operacion`
                                                ]
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                            {!efectivo && (
                                <div className="grid gap-1.5">
                                    <Label>Voucher (captura o PDF)</Label>
                                    <CampoArchivo
                                        acepta="imagen-pdf"
                                        archivos={
                                            operacion.voucher
                                                ? [operacion.voucher]
                                                : []
                                        }
                                        onArchivos={(archivos) =>
                                            cambiar(indice, {
                                                voucher: archivos[0] ?? null,
                                            })
                                        }
                                        progreso={progress?.percentage ?? null}
                                        error={
                                            fallos[
                                                `operaciones.${indice}.voucher`
                                            ]
                                        }
                                        onPreparando={setPreparando}
                                    />
                                </div>
                            )}
                        </fieldset>
                    ))}

                    {!efectivo && (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            onClick={agregar}
                        >
                            <Plus className="size-4" />
                            Agregar otra operación
                        </Button>
                    )}

                    {(varias || sePasa) && (
                        <p
                            className={cn(
                                'text-sm tabular-nums',
                                sePasa
                                    ? 'text-red-700 dark:text-red-400'
                                    : 'text-muted-foreground',
                            )}
                        >
                            Suma {dinero(suma)} de {dinero(porPagar)} por pagar
                            {sePasa
                                ? ': es más de lo que falta.'
                                : porPagar - suma > 0.005
                                  ? ` · quedarán ${dinero(porPagar - suma)} pendientes.`
                                  : ' · el pedido queda pagado.'}
                        </p>
                    )}
                    <InputError message={fallos.operaciones} />

                    <DialogFooter>
                        <Button
                            type="submit"
                            className="h-12"
                            disabled={processing || preparando || sePasa}
                        >
                            {processing && <Spinner />}
                            {varias
                                ? `Anotar las ${operaciones.length} operaciones`
                                : 'Anotar el pago'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
