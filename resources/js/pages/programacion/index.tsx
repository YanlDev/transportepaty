import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Ban, Plus, Undo2 } from 'lucide-react';
import { useState } from 'react';
import novedades from '@/actions/App/Http/Controllers/NovedadController';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import { TablaCargoTransport } from '@/components/programacion/tabla-cargo-transport';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    EnumOption,
    NovedadItem,
    ResultadoProgramacion,
} from '@/types/fleet';

type Props = {
    fecha: string;
    cupos: number;
    resultado: ResultadoProgramacion;
    novedades: NovedadItem[];
    tiposNovedad: EnumOption[];
    unidades: EnumOption[];
};

export default function ProgramacionIndex({
    fecha,
    cupos,
    resultado,
    novedades: lista,
    tiposNovedad,
    unidades,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    const [registrando, setRegistrando] = useState(false);

    const recalcular = (cambios: { fecha?: string; cupos?: number }) =>
        router.get(
            programacion.index().url,
            { fecha, cupos, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Programación" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Programación a mina
                </h1>
                <p className="text-sm text-muted-foreground">
                    {resultado.titulares.length} de {cupos} cupos cubiertos
                    {resultado.en_transito.length > 0 &&
                        ` · ${resultado.en_transito.length} ya en tránsito`}
                    {resultado.reservas.length > 0 &&
                        ` · ${resultado.reservas.length} en reserva`}
                    {resultado.cupos_libres > 0 &&
                        ` · ${resultado.cupos_libres} cupos sin cubrir`}
                </p>
            </div>

            <div className="flex flex-wrap items-end gap-4">
                <div className="grid gap-1.5">
                    <Label htmlFor="fecha">Fecha</Label>
                    <Input
                        id="fecha"
                        type="date"
                        value={fecha}
                        className="w-auto"
                        onChange={(evento) =>
                            recalcular({ fecha: evento.target.value })
                        }
                    />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="cupos">Cupos de mina</Label>
                    <Input
                        id="cupos"
                        type="number"
                        min={0}
                        max={200}
                        value={cupos}
                        className="w-28"
                        onChange={(evento) =>
                            recalcular({ cupos: Number(evento.target.value) })
                        }
                    />
                </div>

                {puedeGestionar && (
                    <Button
                        variant="outline"
                        onClick={() => setRegistrando(true)}
                    >
                        <Plus className="size-4" />
                        Registrar novedad
                    </Button>
                )}
            </div>

            {resultado.cupos_libres > 0 && (
                <div className="flex gap-3 rounded-lg border border-amber-500/40 bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    <p>
                        Quedan {resultado.cupos_libres} cupos sin cubrir: no hay
                        más unidades elegibles. Revisa abajo los motivos por los
                        que las demás no pueden subir.
                    </p>
                </div>
            )}

            <section className="flex flex-col gap-3">
                <h2 className="text-lg font-medium">
                    Tabla para Cargo Transport
                </h2>
                <TablaCargoTransport filas={resultado.tabla} />
            </section>

            {resultado.reservas.length > 0 && (
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Reservas</h2>
                    <p className="text-sm text-muted-foreground">
                        Elegibles que no entraron por cupo. Si mina abre más
                        espacio, suben en este orden.
                    </p>
                    <ListaSimple filas={resultado.reservas} />
                </section>
            )}

            {resultado.no_programables.length > 0 && (
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">
                        No programables ({resultado.no_programables.length})
                    </h2>
                    <ListaSimple filas={resultado.no_programables} conMotivo />
                </section>
            )}

            {lista.length > 0 && (
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Novedades vigentes</h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Unidad</TableHead>
                                    <TableHead>Novedad</TableHead>
                                    <TableHead>Desde</TableHead>
                                    {puedeGestionar && (
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lista.map((novedad) => (
                                    <TableRow key={novedad.id}>
                                        <TableCell className="font-medium">
                                            {novedad.placa}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                <Ban />
                                                {novedad.tipo_label}
                                            </Badge>
                                            {novedad.motivo !==
                                                novedad.tipo_label && (
                                                <span className="ml-2 text-sm text-muted-foreground">
                                                    {novedad.motivo}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>{novedad.desde}</TableCell>
                                        {puedeGestionar && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            novedades.levantar(
                                                                novedad.id,
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Undo2 className="size-4" />
                                                    Levantar
                                                </Button>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>
            )}

            {puedeGestionar && (
                <NovedadDialog
                    abierto={registrando}
                    onCerrar={() => setRegistrando(false)}
                    fecha={fecha}
                    tipos={tiposNovedad}
                    unidades={unidades}
                />
            )}
        </div>
    );
}

function ListaSimple({
    filas,
    conMotivo = false,
}: {
    filas: ResultadoProgramacion['reservas'];
    conMotivo?: boolean;
}) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Vehículo</TableHead>
                        <TableHead>Conductor</TableHead>
                        <TableHead>Carga</TableHead>
                        <TableHead>{conMotivo ? 'Motivo' : 'Estado'}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {filas.map((fila) => (
                        <TableRow key={fila.tracto_id}>
                            <TableCell className="font-medium whitespace-nowrap">
                                {fila.vehiculo}
                            </TableCell>
                            <TableCell>{fila.conductor ?? '—'}</TableCell>
                            <TableCell>{fila.tipo_carga ?? '—'}</TableCell>
                            <TableCell>
                                {conMotivo ? fila.motivo : fila.estado_unidad}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function NovedadDialog({
    abierto,
    onCerrar,
    fecha,
    tipos,
    unidades,
}: {
    abierto: boolean;
    onCerrar: () => void;
    fecha: string;
    tipos: EnumOption[];
    unidades: EnumOption[];
}) {
    const { data, setData, submit, processing, errors, reset } = useForm({
        tracto_id: '',
        tipo: tipos[0]?.value ?? '',
        desde: fecha,
        motivo: '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        submit(novedades.store(), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onCerrar();
            },
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={(open) => !open && onCerrar()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar novedad</DialogTitle>
                </DialogHeader>

                <form onSubmit={enviar} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="tracto_id">Unidad</Label>
                        <Select
                            value={data.tracto_id}
                            onValueChange={(value) =>
                                setData('tracto_id', value)
                            }
                        >
                            <SelectTrigger id="tracto_id" aria-label="Unidad">
                                <SelectValue placeholder="Elige la unidad" />
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
                        {errors.tracto_id && (
                            <p className="text-sm text-destructive">
                                {errors.tracto_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tipo">Tipo</Label>
                        <Select
                            value={data.tipo}
                            onValueChange={(value) => setData('tipo', value)}
                        >
                            <SelectTrigger id="tipo" aria-label="Tipo">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {tipos.map((tipo) => (
                                    <SelectItem
                                        key={tipo.value}
                                        value={tipo.value}
                                    >
                                        {tipo.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="desde">Desde</Label>
                        <Input
                            id="desde"
                            type="date"
                            value={data.desde}
                            onChange={(evento) =>
                                setData('desde', evento.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="motivo">Detalle (opcional)</Label>
                        <Input
                            id="motivo"
                            value={data.motivo}
                            onChange={(evento) =>
                                setData('motivo', evento.target.value)
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCerrar}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Registrar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ProgramacionIndex.layout = {
    breadcrumbs: [{ title: 'Programación', href: programacion.index().url }],
};
