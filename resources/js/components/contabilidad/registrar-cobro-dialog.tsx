import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import cobros from '@/actions/App/Http/Controllers/CobroController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
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
import { formatearFecha } from '@/lib/format';
import type {
    ClienteDeCobro,
    Cobro,
    CuentaDeCobro,
    FacturaPorCobrar,
} from '@/types/contabilidad';

type Props = {
    cuentas: CuentaDeCobro[];
    clientes: ClienteDeCobro[];
    /** Las facturas que todavía deben algo. */
    facturas: FacturaPorCobrar[];
    /**
     * Un cobro con saldo a favor: en vez de registrar un depósito nuevo, se
     * reparte lo que le queda entre más facturas.
     */
    cobro?: Cobro;
    trigger: React.ReactNode;
};

type Formulario = {
    fecha: string;
    cuenta_bancaria_id: string;
    numero_operacion: string;
    monto: string;
    moneda: string;
    cliente_id: string;
    observacion: string;
    aplicaciones: { factura_id: number; monto: string }[];
};

const SIN_CLIENTE = 'todos';

function hoy(): string {
    const fecha = new Date();
    const dos = (n: number) => String(n).padStart(2, '0');

    return `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}`;
}

function dinero(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Registrar la plata que entró: el depósito se escribe una vez y se marca qué
 * facturas paga. A cada factura se le propone lo que le falta, pero se puede
 * poner menos (cobro parcial); nunca más de lo que debe. Lo que sobre del
 * depósito queda a favor del cliente, y un depósito sin ninguna factura es un
 * adelanto.
 */
export function RegistrarCobroDialog({
    cuentas,
    clientes,
    facturas,
    cobro,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [buscar, setBuscar] = useState('');

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<Formulario>({
            fecha: hoy(),
            cuenta_bancaria_id: '',
            numero_operacion: '',
            monto: '',
            moneda: cobro?.moneda ?? 'PEN',
            cliente_id: cobro?.cliente_id ? String(cobro.cliente_id) : '',
            observacion: '',
            aplicaciones: [],
        });

    const cliente = clientes.find((uno) => String(uno.id) === data.cliente_id);
    const simbolo = data.moneda === 'USD' ? '$' : 'S/';

    // Las facturas de esa moneda y, si se eligió, de ese cliente.
    const visibles = useMemo(() => {
        const texto = buscar.trim().toLowerCase();

        return facturas.filter(
            (factura) =>
                factura.moneda === data.moneda &&
                (!cliente?.ruc || factura.cliente_ruc === cliente.ruc) &&
                (texto === '' ||
                    factura.numero.toLowerCase().includes(texto) ||
                    (factura.cliente ?? '').toLowerCase().includes(texto)),
        );
    }, [facturas, data.moneda, cliente, buscar]);

    const disponible = cobro ? cobro.saldo_a_favor : Number(data.monto) || 0;
    const repartido = data.aplicaciones.reduce(
        (suma, aplicacion) => suma + (Number(aplicacion.monto) || 0),
        0,
    );
    const sobra = Math.round((disponible - repartido) * 100) / 100;

    const aplicacionDe = (id: number) =>
        data.aplicaciones.find((aplicacion) => aplicacion.factura_id === id);

    const marcar = (factura: FacturaPorCobrar, marcada: boolean) => {
        if (!marcada) {
            setData(
                'aplicaciones',
                data.aplicaciones.filter(
                    (aplicacion) => aplicacion.factura_id !== factura.id,
                ),
            );

            return;
        }

        // Se propone lo que le falta, sin pasar de lo que queda del depósito.
        const propuesto =
            disponible > 0
                ? Math.min(factura.saldo, Math.max(sobra, 0))
                : factura.saldo;

        setData('aplicaciones', [
            ...data.aplicaciones,
            {
                factura_id: factura.id,
                monto: (propuesto > 0 ? propuesto : factura.saldo).toFixed(2),
            },
        ]);
    };

    const cambiarMonto = (id: number, monto: string) =>
        setData(
            'aplicaciones',
            data.aplicaciones.map((aplicacion) =>
                aplicacion.factura_id === id
                    ? { ...aplicacion, monto }
                    : aplicacion,
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(cobro ? cobros.aplicar(cobro.id).url : cobros.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setBuscar('');
                setOpen(false);
            },
        });
    };

    const errorDe = (id: number) =>
        (errors as Record<string, string | undefined>)[`aplicaciones.${id}`];

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                    clearErrors();
                    setBuscar('');
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {cobro
                                ? 'Aplicar saldo a favor'
                                : 'Registrar cobro'}
                        </DialogTitle>
                        <DialogDescription>
                            {cobro
                                ? `Quedan ${cobro.simbolo} ${dinero(cobro.saldo_a_favor)} de este cobro sin repartir. Marca las facturas que paga.`
                                : 'Escribe el depósito y marca las facturas que paga. Lo que sobre queda a favor del cliente.'}
                        </DialogDescription>
                    </DialogHeader>

                    {!cobro && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Fecha del depósito"
                                error={errors.fecha}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="date"
                                        value={data.fecha}
                                        max={hoy()}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                        required
                                    />
                                )}
                            </Field>
                            <Field
                                label="Monto del depósito"
                                error={errors.monto}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        value={data.monto}
                                        onChange={(e) =>
                                            setData('monto', e.target.value)
                                        }
                                        required
                                    />
                                )}
                            </Field>
                            <Field
                                label="Cuenta"
                                error={errors.cuenta_bancaria_id}
                            >
                                {(id) => (
                                    <Select
                                        value={data.cuenta_bancaria_id}
                                        onValueChange={(valor) => {
                                            const cuenta = cuentas.find(
                                                (una) =>
                                                    String(una.id) === valor,
                                            );

                                            setData((actual) => ({
                                                ...actual,
                                                cuenta_bancaria_id: valor,
                                                // La moneda del cobro es la de la cuenta.
                                                moneda:
                                                    cuenta?.moneda ??
                                                    actual.moneda,
                                                aplicaciones:
                                                    cuenta &&
                                                    cuenta.moneda !==
                                                        actual.moneda
                                                        ? []
                                                        : actual.aplicaciones,
                                            }));
                                        }}
                                    >
                                        <SelectTrigger
                                            id={id}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Por dónde entró" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {cuentas.map((cuenta) => (
                                                <SelectItem
                                                    key={cuenta.id}
                                                    value={String(cuenta.id)}
                                                >
                                                    {cuenta.alias}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <Field
                                label="N° de operación"
                                error={errors.numero_operacion}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={data.numero_operacion}
                                        maxLength={30}
                                        onChange={(e) =>
                                            setData(
                                                'numero_operacion',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                label="Cliente"
                                error={errors.cliente_id}
                                ayuda="Hace falta si sobra saldo o es un adelanto."
                            >
                                {(id) => (
                                    <Select
                                        value={data.cliente_id || SIN_CLIENTE}
                                        onValueChange={(valor) =>
                                            setData((actual) => ({
                                                ...actual,
                                                cliente_id:
                                                    valor === SIN_CLIENTE
                                                        ? ''
                                                        : valor,
                                                aplicaciones: [],
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            id={id}
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={SIN_CLIENTE}>
                                                Todos los clientes
                                            </SelectItem>
                                            {clientes.map((uno) => (
                                                <SelectItem
                                                    key={uno.id}
                                                    value={String(uno.id)}
                                                >
                                                    {uno.alias}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <Field
                                label="Observación"
                                error={errors.observacion}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={data.observacion}
                                        maxLength={500}
                                        onChange={(e) =>
                                            setData(
                                                'observacion',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                    )}

                    <div className="flex flex-col gap-2">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm font-medium">
                                Facturas que paga
                            </p>
                            <Input
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Buscar factura o cliente"
                                className="h-8 max-w-56"
                                aria-label="Buscar factura o cliente"
                            />
                        </div>

                        <ul className="flex max-h-64 flex-col divide-y overflow-y-auto rounded-lg border">
                            {visibles.length === 0 && (
                                <li className="p-3 text-sm text-muted-foreground">
                                    No hay facturas por cobrar
                                    {cliente ? ` de ${cliente.alias}` : ''}.
                                </li>
                            )}
                            {visibles.map((factura) => {
                                const aplicacion = aplicacionDe(factura.id);

                                return (
                                    <li
                                        key={factura.id}
                                        className="flex flex-col gap-1 p-2 text-sm"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                checked={
                                                    aplicacion !== undefined
                                                }
                                                onCheckedChange={(marcada) =>
                                                    marcar(
                                                        factura,
                                                        marcada === true,
                                                    )
                                                }
                                                aria-label={`Pagar la factura ${factura.numero}`}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="flex flex-wrap gap-x-2">
                                                    <span className="font-mono font-medium">
                                                        {factura.numero}
                                                    </span>
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {formatearFecha(
                                                            factura.fecha_emision,
                                                        )}
                                                    </span>
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {factura.cliente ??
                                                        'Sin cliente'}
                                                </p>
                                            </div>
                                            <span className="text-right text-xs text-muted-foreground tabular-nums">
                                                debe {simbolo}{' '}
                                                {dinero(factura.saldo)}
                                                {factura.saldo <
                                                    factura.neto && (
                                                    <span className="block">
                                                        de{' '}
                                                        {dinero(factura.neto)}
                                                    </span>
                                                )}
                                            </span>
                                            {aplicacion && (
                                                <Input
                                                    type="number"
                                                    inputMode="decimal"
                                                    step="0.01"
                                                    min="0.01"
                                                    max={factura.saldo}
                                                    value={aplicacion.monto}
                                                    onChange={(e) =>
                                                        cambiarMonto(
                                                            factura.id,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-8 w-28 text-right tabular-nums"
                                                    aria-label={`Monto para la factura ${factura.numero}`}
                                                />
                                            )}
                                        </div>
                                        <InputError
                                            message={errorDe(factura.id)}
                                        />
                                    </li>
                                );
                            })}
                        </ul>

                        <p className="flex flex-wrap justify-between gap-x-4 text-sm tabular-nums">
                            <span>
                                Repartido: {simbolo} {dinero(repartido)}
                                {data.aplicaciones.length > 0 &&
                                    ` en ${data.aplicaciones.length} ${data.aplicaciones.length === 1 ? 'factura' : 'facturas'}`}
                            </span>
                            {disponible > 0 && (
                                <span
                                    className={
                                        sobra < 0
                                            ? 'font-medium text-destructive'
                                            : sobra > 0
                                              ? 'text-amber-700 dark:text-amber-500'
                                              : 'text-muted-foreground'
                                    }
                                >
                                    {sobra < 0
                                        ? `Se pasa por ${simbolo} ${dinero(-sobra)}`
                                        : sobra > 0
                                          ? `Queda a favor: ${simbolo} ${dinero(sobra)}`
                                          : 'Todo repartido'}
                                </span>
                            )}
                        </p>
                        <InputError
                            message={
                                cobro
                                    ? (errors.monto ??
                                      (
                                          errors as Record<
                                              string,
                                              string | undefined
                                          >
                                      ).aplicaciones)
                                    : undefined
                            }
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                sobra < 0 ||
                                (cobro !== undefined &&
                                    data.aplicaciones.length === 0)
                            }
                        >
                            {processing && <Spinner />}
                            {cobro ? 'Aplicar' : 'Registrar cobro'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
