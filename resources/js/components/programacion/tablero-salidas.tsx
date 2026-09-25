import { PencilSimple, Trash, WhatsappLogo } from '@phosphor-icons/react';
import {
    abrirWhatsapp,
    AvisoSalida,
} from '@/components/programacion/aviso-salida';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ClienteChip } from '@/components/viajes/cliente-chip';
import type {
    AvisoOperaciones,
    EstadoSalida,
    ProgramacionTarjeta,
} from '@/types/programacion';

const ESTADOS: Record<EstadoSalida, { texto: string; tono: StatusTone }> = {
    despachado: { texto: 'Salió', tono: 'success' },
    programado: { texto: 'Programado', tono: 'neutral' },
    sin_gr: { texto: 'Sin GR', tono: 'danger' },
};

/**
 * Las salidas del día: una fila por unidad, con su cliente, su destino, el
 * preaviso al conductor y si ya salió.
 *
 * En pantalla ancha es una tabla; en el teléfono, una tarjeta por unidad, que
 * es como lo mira quien está en el patio.
 */
export function TableroSalidas({
    programaciones,
    avisoOperaciones,
    advertencia,
    editable,
    onEditar,
    onBorrar,
}: {
    programaciones: ProgramacionTarjeta[];
    avisoOperaciones: AvisoOperaciones;
    /** La advertencia de documentación, igual para todas las salidas. */
    advertencia: string;
    editable: boolean;
    onEditar: (programacion: ProgramacionTarjeta) => void;
    onBorrar: (programacion: ProgramacionTarjeta) => void;
}) {
    const salieron = programaciones.filter(
        (programacion) => programacion.estado === 'despachado',
    ).length;

    const puedeAvisarOperaciones =
        editable &&
        avisoOperaciones.whatsapp !== null &&
        programaciones.length > 0;

    return (
        <section aria-label="Salidas del día" className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {salieron} de {programaciones.length}{' '}
                    {programaciones.length === 1
                        ? 'unidad salió'
                        : 'unidades salieron'}
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    {/* El resumen del día al número de operaciones: quién sale y
                    qué unidades siguen sin GR, para que alguien fuera del
                    patio lo sepa antes de que una avance sin guía. */}
                    {puedeAvisarOperaciones && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                abrirWhatsapp(
                                    avisoOperaciones.whatsapp as string,
                                    avisoOperaciones.mensaje,
                                )
                            }
                            title="Mandar el resumen del día al WhatsApp de operaciones"
                        >
                            <WhatsappLogo weight="fill" className="size-4" />
                            Resumen a operaciones
                        </Button>
                    )}
                </div>
            </div>

            {/* Teléfono: una tarjeta por unidad. */}
            <ul className="flex flex-col gap-2 md:hidden">
                {programaciones.map((programacion) => (
                    <li
                        key={programacion.id}
                        className="flex flex-col gap-2 rounded-xl border bg-card p-3 shadow-sm"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono font-semibold">
                                {programacion.placa}
                            </span>
                            <Estado programacion={programacion} />
                        </div>

                        <ClienteChip cliente={programacion.cliente} />

                        <p className="text-sm">{programacion.destino}</p>
                        <p className="text-xs text-muted-foreground">
                            {programacion.conductor}
                        </p>

                        <div className="flex items-center justify-between gap-2">
                            <AvisoSalida
                                tarjeta={programacion}
                                advertencia={advertencia}
                                editable={editable}
                            />
                            {editable && (
                                <Acciones
                                    programacion={programacion}
                                    onEditar={() => onEditar(programacion)}
                                    onBorrar={() => onBorrar(programacion)}
                                />
                            )}
                        </div>
                    </li>
                ))}
            </ul>

            {/* Escritorio: la misma información en tabla. */}
            <div className="hidden overflow-x-auto rounded-xl border shadow-sm md:block">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Unidad</TableHead>
                            <TableHead>Cliente</TableHead>
                            <TableHead>Destino</TableHead>
                            <TableHead>Conductor</TableHead>
                            <TableHead>Aviso</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead className="w-0" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {programaciones.map((programacion) => (
                            <TableRow key={programacion.id}>
                                <TableCell className="font-mono font-medium whitespace-nowrap">
                                    {programacion.placa}
                                </TableCell>
                                <TableCell className="max-w-[180px]">
                                    <ClienteChip
                                        cliente={programacion.cliente}
                                    />
                                </TableCell>
                                <TableCell
                                    className="max-w-[220px] truncate"
                                    title={programacion.destino}
                                >
                                    {programacion.destino}
                                </TableCell>
                                <TableCell
                                    className="max-w-[200px] truncate text-muted-foreground"
                                    title={programacion.conductor}
                                >
                                    {programacion.conductor}
                                </TableCell>
                                <TableCell>
                                    <AvisoSalida
                                        tarjeta={programacion}
                                        advertencia={advertencia}
                                        editable={editable}
                                    />
                                </TableCell>
                                <TableCell className="whitespace-nowrap">
                                    <Estado programacion={programacion} />
                                </TableCell>
                                <TableCell className="w-0">
                                    {editable && (
                                        <Acciones
                                            programacion={programacion}
                                            onEditar={() =>
                                                onEditar(programacion)
                                            }
                                            onBorrar={() =>
                                                onBorrar(programacion)
                                            }
                                        />
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}

/** El estado de la salida, con el N° de GR debajo cuando ya salió. */
function Estado({ programacion }: { programacion: ProgramacionTarjeta }) {
    const estado = ESTADOS[programacion.estado];

    return (
        <span className="flex flex-col items-start gap-0.5">
            <StatusBadge label={estado.texto} tone={estado.tono} />
            {programacion.numero_gr && (
                <span className="font-mono text-[11px] text-muted-foreground">
                    {programacion.numero_gr}
                </span>
            )}
        </span>
    );
}

function Acciones({
    programacion,
    onEditar,
    onBorrar,
}: {
    programacion: ProgramacionTarjeta;
    onEditar: () => void;
    onBorrar: () => void;
}) {
    return (
        <div className="flex justify-end gap-1">
            <Button
                variant="ghost"
                size="icon"
                onClick={onEditar}
                aria-label={`Editar la programación de ${programacion.placa}`}
            >
                <PencilSimple className="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                onClick={onBorrar}
                aria-label={`Quitar la programación de ${programacion.placa}`}
                className="text-muted-foreground hover:text-destructive"
            >
                <Trash className="size-4" />
            </Button>
        </div>
    );
}
