import { Head, Link, useForm } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { useState } from 'react';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import detracciones from '@/actions/App/Http/Controllers/DetraccionController';
import { update } from '@/actions/App/Http/Controllers/FacturaController';
import { EmptyState } from '@/components/empty-state';
import { ListadoResponsivo } from '@/components/listado-responsivo';
import { Button } from '@/components/ui/button';
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
import { Spinner } from '@/components/ui/spinner';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';

type Pendiente = {
    id: number;
    numero: string;
    cliente: string | null;
    fecha_emision: string;
    total: number;
    detraccion: number;
    /** El cliente ya pagó el total: toca depositarla. */
    cobrada: boolean;
    fecha_pago: string | null;
    dias_desde_el_cobro: number | null;
    registrable: boolean;
};

type Props = {
    pendientes: Pendiente[];
    totales: {
        por_depositar: number;
        facturas_por_depositar: number;
        sin_cobrar: number;
        facturas_sin_cobrar: number;
    };
};

function dinero(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function hoy(): string {
    const fecha = new Date();
    const dos = (n: number) => String(n).padStart(2, '0');

    return `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}`;
}

/** Registrar que la empresa ya depositó la detracción, con su constancia. */
function RegistrarDeposito({ factura }: { factura: Pendiente }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset, clearErrors } =
        useForm({ fecha_detraccion: hoy(), constancia_detraccion: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        patch(update(factura.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Registrar depósito
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Detracción de la factura {factura.numero}
                        </DialogTitle>
                        <DialogDescription>
                            S/ {dinero(factura.detraccion)} depositados en el
                            Banco de la Nación por cuenta de{' '}
                            {factura.cliente ?? 'el cliente'}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Fecha del depósito"
                            error={errors.fecha_detraccion}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="date"
                                    value={data.fecha_detraccion}
                                    max={hoy()}
                                    onChange={(e) =>
                                        setData(
                                            'fecha_detraccion',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            )}
                        </Field>
                        <Field
                            label="N° de constancia"
                            error={errors.constancia_detraccion}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.constancia_detraccion}
                                    maxLength={30}
                                    onChange={(e) =>
                                        setData(
                                            'constancia_detraccion',
                                            e.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Registrar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Si ya toca depositarla y hace cuánto. */
function Situacion({ factura }: { factura: Pendiente }) {
    if (!factura.cobrada) {
        return (
            <span className="text-muted-foreground">
                El cliente todavía no paga
            </span>
        );
    }

    const dias = factura.dias_desde_el_cobro ?? 0;

    return (
        <span
            className={cn(
                'font-medium',
                dias > 7
                    ? 'text-red-700 dark:text-red-400'
                    : 'text-amber-700 dark:text-amber-500',
            )}
        >
            Cobrada el {formatearFecha(factura.fecha_pago)}
            {dias > 0 && ` · hace ${dias} ${dias === 1 ? 'día' : 'días'}`}
        </span>
    );
}

export default function Detracciones({ pendientes, totales }: Props) {
    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <Head title="Detracciones por depositar" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-lg font-semibold">
                        Detracciones por depositar
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Las que deposita la empresa por los clientes que le
                        pagan el total. La factura ya está pagada: esto es lo
                        que falta pasar al Banco de la Nación.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href={contabilidad.index()}>Ver cobranza</Link>
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs text-muted-foreground">
                        Toca depositar (el cliente ya pagó)
                    </p>
                    <p className="text-xl font-semibold text-violet-700 tabular-nums dark:text-violet-400">
                        S/ {dinero(totales.por_depositar)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {totales.facturas_por_depositar}{' '}
                        {totales.facturas_por_depositar === 1
                            ? 'factura'
                            : 'facturas'}
                    </p>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs text-muted-foreground">
                        Vendrán cuando el cliente pague
                    </p>
                    <p className="text-xl font-semibold tabular-nums">
                        S/ {dinero(totales.sin_cobrar)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {totales.facturas_sin_cobrar}{' '}
                        {totales.facturas_sin_cobrar === 1
                            ? 'factura'
                            : 'facturas'}
                    </p>
                </div>
            </div>

            <ListadoResponsivo
                items={pendientes}
                clave={(factura) => factura.id}
                vacio={
                    <EmptyState
                        expandir={false}
                        icono={<Landmark className="size-7" />}
                        titulo="No hay detracciones por depositar"
                        descripcion="Todas las que le tocaban a la empresa ya tienen su depósito registrado."
                    />
                }
                tarjeta={(factura) => (
                    <div className="flex flex-col gap-2 rounded-xl border bg-card p-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono font-medium">
                                {factura.numero}
                            </span>
                            <span className="text-base font-semibold tabular-nums">
                                S/ {dinero(factura.detraccion)}
                            </span>
                        </div>
                        <p className="truncate text-muted-foreground">
                            {factura.cliente ?? 'Sin cliente'}
                        </p>
                        <Situacion factura={factura} />
                        {factura.registrable && (
                            <div>
                                <RegistrarDeposito factura={factura} />
                            </div>
                        )}
                    </div>
                )}
                encabezado={
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Factura</TableHead>
                        <TableHead>Cliente</TableHead>
                        <TableHead>Emisión</TableHead>
                        <TableHead className="text-right">Total</TableHead>
                        <TableHead className="text-right">Detracción</TableHead>
                        <TableHead>Situación</TableHead>
                        <TableHead />
                    </TableRow>
                }
                fila={(factura) => (
                    <TableRow>
                        <TableCell className="font-mono font-medium whitespace-nowrap">
                            {factura.numero}
                        </TableCell>
                        <TableCell className="max-w-56 truncate">
                            {factura.cliente ?? '—'}
                        </TableCell>
                        <TableCell className="whitespace-nowrap tabular-nums">
                            {formatearFecha(factura.fecha_emision)}
                        </TableCell>
                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                            {dinero(factura.total)}
                        </TableCell>
                        <TableCell className="text-right font-medium whitespace-nowrap tabular-nums">
                            {dinero(factura.detraccion)}
                        </TableCell>
                        <TableCell className="text-xs whitespace-nowrap">
                            <Situacion factura={factura} />
                        </TableCell>
                        <TableCell className="text-right">
                            {factura.registrable && (
                                <RegistrarDeposito factura={factura} />
                            )}
                        </TableCell>
                    </TableRow>
                )}
            />
        </div>
    );
}

Detracciones.layout = {
    breadcrumbs: [
        { title: 'Contabilidad', href: contabilidad.index().url },
        { title: 'Detracciones', href: detracciones.index().url },
    ],
};
