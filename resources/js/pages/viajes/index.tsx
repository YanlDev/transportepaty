import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Pencil, Plus, Route } from 'lucide-react';
import { useEffect, useState } from 'react';
import viajes, {
    create,
    edit,
} from '@/actions/App/Http/Controllers/ViajeController';
import { FiltrosBarra } from '@/components/filtros-barra';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { EnumOption, Paginator, ViajeListItem } from '@/types/fleet';

type Filtros = {
    buscar: string;
    estado: string;
    carga: string;
};

type Props = {
    viajes: Paginator<ViajeListItem>;
    filtros: Filtros;
    cargas: EnumOption[];
};

const TODAS_LAS_CARGAS = 'todas';
const ESTADO_POR_DEFECTO = 'todos';

const estadoOpciones = [
    { value: ESTADO_POR_DEFECTO, label: 'Todos los viajes' },
    { value: 'en_curso', label: 'En curso' },
    { value: 'completados', label: 'Completados' },
];

export default function ViajesIndex({
    viajes: paginador,
    filtros,
    cargas,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    const aplicar = (cambios: Partial<Filtros>) =>
        router.get(
            viajes.index().url,
            { ...filtros, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        if (buscar === (filtros.buscar ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                viajes.index().url,
                { ...filtros, buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Viajes" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Viajes
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1 ? 'viaje' : 'viajes'} con sus
                        guías de remisión
                    </p>
                </div>

                {puedeGestionar && (
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo viaje
                        </Link>
                    </Button>
                )}
            </div>

            <FiltrosBarra
                buscar={buscar}
                onBuscar={setBuscar}
                placeholder="Buscar por guía, placa o conductor..."
                etiquetaBusqueda="Buscar viajes"
                activos={
                    (filtros.carga ? 1 : 0) +
                    (filtros.estado === ESTADO_POR_DEFECTO ? 0 : 1)
                }
                onLimpiar={() =>
                    aplicar({ carga: '', estado: ESTADO_POR_DEFECTO })
                }
            >
                <Select
                    value={filtros.carga || TODAS_LAS_CARGAS}
                    onValueChange={(value) =>
                        aplicar({
                            carga: value === TODAS_LAS_CARGAS ? '' : value,
                        })
                    }
                >
                    <SelectTrigger
                        aria-label="Tipo de carga"
                        className="h-9 w-full sm:w-auto sm:min-w-40"
                    >
                        <SelectValue placeholder="Carga" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={TODAS_LAS_CARGAS}>
                            Todas las cargas
                        </SelectItem>
                        {cargas.map((carga) => (
                            <SelectItem key={carga.value} value={carga.value}>
                                {carga.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select
                    value={filtros.estado}
                    onValueChange={(value) => aplicar({ estado: value })}
                >
                    <SelectTrigger
                        aria-label="Estado del viaje"
                        className="h-9 w-full sm:w-auto sm:min-w-40"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {estadoOpciones.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                {opcion.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FiltrosBarra>

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center bg-muted text-muted-foreground">
                        <Route className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron viajes</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda
                        {puedeGestionar && ' o registra el primer viaje'}.
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Unidad</TableHead>
                                <TableHead>Carga</TableHead>
                                <TableHead>Ruta</TableHead>
                                <TableHead>Fechas</TableHead>
                                <TableHead>Guías</TableHead>
                                {puedeGestionar && (
                                    <TableHead className="text-right">
                                        Acciones
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {paginador.data.map((viaje) => (
                                <TableRow key={viaje.id}>
                                    <TableCell className="whitespace-nowrap">
                                        <div className="font-medium">
                                            {formatearPlaca(viaje.tracto.placa)}
                                            {viaje.carreta && (
                                                <span className="text-muted-foreground">
                                                    {' / '}
                                                    {formatearPlaca(
                                                        viaje.carreta.placa,
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                        {viaje.conductor && (
                                            <div className="text-xs text-muted-foreground">
                                                {viaje.conductor}
                                            </div>
                                        )}
                                    </TableCell>

                                    <TableCell>
                                        <div>{viaje.tipo_carga_label}</div>
                                        {viaje.fase_label && (
                                            <div className="text-xs text-muted-foreground">
                                                {viaje.fase_label}
                                            </div>
                                        )}
                                    </TableCell>

                                    <TableCell className="whitespace-nowrap">
                                        {viaje.origen} ⇒ {viaje.destino}
                                    </TableCell>

                                    <TableCell className="whitespace-nowrap">
                                        <div className="text-sm">
                                            {formatearFecha(viaje.fecha_salida)}
                                        </div>
                                        {viaje.en_curso ? (
                                            <Badge
                                                variant="secondary"
                                                className="mt-1"
                                            >
                                                En curso · {viaje.dias} d
                                            </Badge>
                                        ) : (
                                            <div className="text-xs text-muted-foreground">
                                                Llegó{' '}
                                                {formatearFecha(
                                                    viaje.fecha_llegada,
                                                )}{' '}
                                                · {viaje.dias} d
                                            </div>
                                        )}
                                    </TableCell>

                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {viaje.guias.map((guia) => (
                                                <Badge
                                                    key={guia.tipo}
                                                    variant={
                                                        guia.numero
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                    title={
                                                        guia.numero ??
                                                        `Sin ${guia.abreviatura}`
                                                    }
                                                >
                                                    {guia.url && <FileText />}
                                                    {guia.abreviatura}
                                                    {guia.numero
                                                        ? ` ${guia.numero}`
                                                        : ''}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>

                                    {puedeGestionar && (
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                aria-label={`Editar viaje de ${viaje.tracto.placa}`}
                                            >
                                                <Link href={edit(viaje.id)}>
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

ViajesIndex.layout = {
    breadcrumbs: [{ title: 'Viajes', href: viajes.index().url }],
};
