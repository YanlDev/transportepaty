import { Head, Link } from '@inertiajs/react';
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cuentasBancarias from '@/actions/App/Http/Controllers/CuentaBancariaController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { CuentaDialog } from '@/components/contabilidad/cuenta-dialog';
import { Copiable } from '@/components/copiable';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CuentaBancaria } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';

type Props = {
    cuentas: CuentaBancaria[];
    monedas: EnumOption[];
};

export default function CuentasBancarias({ cuentas, monedas }: Props) {
    const [enEdicion, setEnEdicion] = useState<CuentaBancaria | null>(null);
    const [abierto, setAbierto] = useState(false);

    const abrir = (cuenta: CuentaBancaria | null) => {
        setEnEdicion(cuenta);
        setAbierto(true);
    };

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <Head title="Cuentas de la empresa" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-lg font-semibold">
                        Cuentas de la empresa
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Es la lista que alimenta la columna «entidad» de la
                        cobranza: por dónde entró cada pago.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={contabilidad.index()}>Ver cobranza</Link>
                    </Button>
                    <Button onClick={() => abrir(null)}>
                        <Plus className="size-4" />
                        Nueva cuenta
                    </Button>
                </div>
            </div>

            {cuentas.length === 0 ? (
                <EmptyState
                    expandir={false}
                    icono={<Landmark className="size-7" />}
                    titulo="Todavía no hay cuentas"
                    descripcion="Registra las cuentas por las que cobras para poder marcar los pagos en la cobranza."
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Alias</TableHead>
                                <TableHead>Banco</TableHead>
                                <TableHead>N° de cuenta</TableHead>
                                <TableHead>CCI</TableHead>
                                <TableHead>Moneda</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">
                                    Facturas
                                </TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cuentas.map((cuenta) => (
                                <TableRow key={cuenta.id}>
                                    <TableCell className="font-medium">
                                        {cuenta.alias}
                                    </TableCell>
                                    <TableCell>{cuenta.banco}</TableCell>
                                    <TableCell className="font-mono text-[11px] tabular-nums">
                                        <Copiable
                                            valor={cuenta.numero_cuenta}
                                            etiqueta="N° de cuenta"
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                        {cuenta.cci ? (
                                            <Copiable
                                                valor={cuenta.cci}
                                                etiqueta="CCI"
                                            />
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>{cuenta.moneda_label}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={
                                                cuenta.activa
                                                    ? 'Activa'
                                                    : 'Cerrada'
                                            }
                                            tone={
                                                cuenta.activa
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {cuenta.facturas_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 text-muted-foreground"
                                                aria-label={`Editar ${cuenta.alias}`}
                                                onClick={() => abrir(cuenta)}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <EliminarCuenta cuenta={cuenta} />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            {/* `key` por cuenta: cada una monta su propio diálogo, así el
                formulario nace con sus valores y no hay que resembrarlo. */}
            <CuentaDialog
                key={enEdicion?.id ?? 'nueva'}
                open={abierto}
                onOpenChange={setAbierto}
                cuenta={enEdicion}
                monedas={monedas}
            />
        </div>
    );
}

/**
 * Una cuenta con facturas encima no se borra —dejaría sin rastro por dónde se
 * cobraron—; lo que corresponde ahí es cerrarla desde la edición, que la saca
 * de los selectores sin tocar el historial.
 */
function EliminarCuenta({ cuenta }: { cuenta: CuentaBancaria }) {
    // Una cuenta con facturas encima no se borra: se desactiva desde el
    // diálogo de edición, así el historial de cobranza sigue apuntando a algo.
    if (cuenta.facturas_count > 0) {
        return null;
    }

    return (
        <ConfirmarBorradoDialog
            url={cuentasBancarias.destroy(cuenta.id).url}
            titulo="Eliminar cuenta"
            descripcion={
                <>
                    ¿Seguro que deseas eliminar la cuenta{' '}
                    <Resaltado>{cuenta.alias}</Resaltado>? Todavía no tiene
                    facturas asociadas, así que no se pierde nada del historial.
                </>
            }
            trigger={
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground hover:text-destructive"
                    aria-label={`Eliminar ${cuenta.alias}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            }
        />
    );
}

CuentasBancarias.layout = {
    breadcrumbs: [
        { title: 'Contabilidad', href: contabilidad.index().url },
        { title: 'Cuentas', href: cuentasBancarias.index().url },
    ],
};
