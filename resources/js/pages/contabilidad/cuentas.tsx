import { Head, Link } from '@inertiajs/react';
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cuentasBancarias from '@/actions/App/Http/Controllers/CuentaBancariaController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { CuentaDialog } from '@/components/contabilidad/cuenta-dialog';
import { Copiable } from '@/components/copiable';
import { EmptyState } from '@/components/empty-state';
import { ListadoResponsivo } from '@/components/listado-responsivo';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
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
                    <Button
                        onClick={() => abrir(null)}
                        className="max-md:hidden"
                    >
                        <Plus className="size-4" />
                        Nueva cuenta
                    </Button>
                </div>
            </div>

            <ListadoResponsivo
                items={cuentas}
                clave={(cuenta) => cuenta.id}
                vacio={
                    <EmptyState
                        expandir={false}
                        icono={<Landmark className="size-7" />}
                        titulo="Todavía no hay cuentas"
                        descripcion="Registra las cuentas por las que cobras para poder marcar los pagos en la cobranza."
                    />
                }
                tarjeta={(cuenta) => (
                    <div className="flex flex-col gap-2 rounded-xl border bg-card p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="truncate text-base font-semibold">
                                {cuenta.alias}
                            </span>
                            <EstadoCuenta activa={cuenta.activa} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {cuenta.banco} · {cuenta.moneda_label}
                        </p>
                        <div className="font-mono text-sm tabular-nums">
                            <Copiable
                                valor={cuenta.numero_cuenta}
                                etiqueta="N° de cuenta"
                            />
                        </div>
                        {cuenta.cci && (
                            <div className="font-mono text-xs text-muted-foreground tabular-nums">
                                CCI{' '}
                                <Copiable valor={cuenta.cci} etiqueta="CCI" />
                            </div>
                        )}
                        <div className="flex items-center justify-between gap-2 border-t pt-2">
                            <span className="text-xs text-muted-foreground">
                                {cuenta.facturas_count} facturas
                            </span>
                            <AccionesCuenta
                                cuenta={cuenta}
                                onEditar={() => abrir(cuenta)}
                            />
                        </div>
                    </div>
                )}
                encabezado={
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Alias</TableHead>
                        <TableHead>Banco</TableHead>
                        <TableHead>N° de cuenta</TableHead>
                        <TableHead>CCI</TableHead>
                        <TableHead>Moneda</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead className="text-right">Facturas</TableHead>
                        <TableHead className="w-0" />
                    </TableRow>
                }
                fila={(cuenta) => (
                    <TableRow>
                        <TableCell className="font-medium">
                            {cuenta.alias}
                        </TableCell>
                        <TableCell>{cuenta.banco}</TableCell>
                        <TableCell className="font-mono text-xs tabular-nums">
                            <Copiable
                                valor={cuenta.numero_cuenta}
                                etiqueta="N° de cuenta"
                            />
                        </TableCell>
                        <TableCell className="font-mono text-xs text-muted-foreground tabular-nums">
                            {cuenta.cci ? (
                                <Copiable valor={cuenta.cci} etiqueta="CCI" />
                            ) : (
                                '—'
                            )}
                        </TableCell>
                        <TableCell>{cuenta.moneda_label}</TableCell>
                        <TableCell>
                            <EstadoCuenta activa={cuenta.activa} />
                        </TableCell>
                        <TableCell className="text-right text-muted-foreground tabular-nums">
                            {cuenta.facturas_count}
                        </TableCell>
                        <TableCell>
                            <AccionesCuenta
                                cuenta={cuenta}
                                onEditar={() => abrir(cuenta)}
                            />
                        </TableCell>
                    </TableRow>
                )}
            />

            <AccionPrincipalMovil onClick={() => abrir(null)} icono={<Plus />}>
                Nueva cuenta
            </AccionPrincipalMovil>

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

function EstadoCuenta({ activa }: { activa: boolean }) {
    return (
        <StatusBadge
            label={activa ? 'Activa' : 'Cerrada'}
            tone={activa ? 'success' : 'neutral'}
        />
    );
}

function AccionesCuenta({
    cuenta,
    onEditar,
}: {
    cuenta: CuentaBancaria;
    onEditar: () => void;
}) {
    return (
        <div className="flex items-center justify-end gap-1">
            <Button
                variant="ghost"
                size="icon"
                className="size-8 text-muted-foreground"
                aria-label={`Editar ${cuenta.alias}`}
                onClick={onEditar}
            >
                <Pencil className="size-4" />
            </Button>
            <EliminarCuenta cuenta={cuenta} />
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
