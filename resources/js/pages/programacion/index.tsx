import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, List, Search } from 'lucide-react';
import { useState } from 'react';
import programacion, {
    destroy,
    marcar,
} from '@/actions/App/Http/Controllers/ProgramacionController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import { cn } from '@/lib/utils';
import type {
    EnumOption,
    EstadoProgramacion,
    ProgramacionFila,
} from '@/types/fleet';

type Filtros = {
    caja: string;
};

type Props = {
    fecha: string;
    filtros: Filtros;
    cajas: EnumOption[];
    clientes: string[];
    filas: ProgramacionFila[];
};

// Radix Select no admite items con value vacío.
const TODAS_LAS_CAJAS = 'todas';
const CLIENTE_SIN_ELEGIR = 'sin_cliente';
const CLIENTE_OTRO = 'otro';

const estadoConfig: Record<
    EstadoProgramacion,
    { label: string; badge: string }
> = {
    metalico: {
        label: 'Metálico',
        badge: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    },
    concentrado: {
        label: 'Concentrado',
        badge: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
    },
    escoria: {
        label: 'Escoria',
        badge: 'bg-stone-100 text-stone-800 dark:bg-stone-800 dark:text-stone-300',
    },
    ransa: {
        label: 'Ransa',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    polytex: {
        label: 'Polytex',
        badge: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    },
    particular: {
        label: 'Particular',
        badge: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    },
    salida: {
        label: 'Salida',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
    libre: {
        label: 'Libre',
        badge: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    },
};

const CELDA_CON_BORDE = 'border border-border';

export default function ProgramacionIndex({
    fecha,
    filtros,
    cajas,
    clientes,
    filas,
}: Props) {
    const [filtro, setFiltro] = useState<EstadoProgramacion | 'todos'>('todos');
    const [buscar, setBuscar] = useState('');

    const aplicar = (cambios: Partial<{ fecha: string; caja: string }>) => {
        router.get(
            programacion.index().url,
            { fecha, caja: filtros.caja || undefined, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sumarDias = (cantidad: number) => {
        const [anio, mes, dia] = fecha.split('-').map(Number);
        const siguiente = new Date(anio, mes - 1, dia + cantidad);
        aplicar({
            fecha: `${siguiente.getFullYear()}-${String(siguiente.getMonth() + 1).padStart(2, '0')}-${String(siguiente.getDate()).padStart(2, '0')}`,
        });
    };

    const busquedaNormalizada = buscar.trim().toLowerCase();

    const filasFiltradas = filas.filter((fila) => {
        if (filtro !== 'todos' && fila.marca?.estado !== filtro) {
            return false;
        }

        if (busquedaNormalizada === '') {
            return true;
        }

        return [
            fila.placa,
            fila.conductor_nombre,
            fila.marca?.estado_label,
            fila.marca?.destino,
            fila.marca?.cliente,
        ].some((campo) => campo?.toLowerCase().includes(busquedaNormalizada));
    });

    const conteos = filas.reduce(
        (acumulado, fila) => {
            if (fila.marca) {
                acumulado[fila.marca.estado] =
                    (acumulado[fila.marca.estado] ?? 0) + 1;
            }

            return acumulado;
        },
        {} as Partial<Record<EstadoProgramacion, number>>,
    );

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Programación" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Programación
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Qué carga tiene programada cada tracto según los avisos
                        de WhatsApp. Click en una fila para marcarla.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => sumarDias(-1)}
                        aria-label="Día anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="min-w-[8rem] text-center text-sm font-medium tabular-nums">
                        {formatearFecha(fecha)}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => sumarDias(1)}
                        aria-label="Día siguiente"
                    >
                        <ChevronRight className="size-4" />
                    </Button>

                    <Select
                        value={filtros.caja || TODAS_LAS_CAJAS}
                        onValueChange={(value) =>
                            aplicar({
                                caja: value === TODAS_LAS_CAJAS ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label="Caja"
                            className="h-9 w-full sm:w-auto sm:min-w-36"
                        >
                            <SelectValue placeholder="Caja" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODAS_LAS_CAJAS}>
                                Todas las cajas
                            </SelectItem>
                            {cajas.map((caja) => (
                                <SelectItem key={caja.value} value={caja.value}>
                                    {caja.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="relative max-w-sm">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar por placa, conductor, programación o cliente..."
                    aria-label="Buscar en la programación"
                    className="h-9 pl-8"
                />
            </div>

            <div className="flex flex-wrap items-center gap-1.5">
                <FiltroBoton
                    activo={filtro === 'todos'}
                    onClick={() => setFiltro('todos')}
                >
                    Todos ({filas.length})
                </FiltroBoton>
                {(Object.keys(estadoConfig) as EstadoProgramacion[]).map(
                    (estado) => (
                        <FiltroBoton
                            key={estado}
                            activo={filtro === estado}
                            onClick={() => setFiltro(estado)}
                            badge={estadoConfig[estado].badge}
                        >
                            {estadoConfig[estado].label} ({conteos[estado] ?? 0}
                            )
                        </FiltroBoton>
                    ),
                )}
            </div>

            <div className="overflow-x-auto border border-border">
                <Table className="border-collapse">
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead
                                className={cn(CELDA_CON_BORDE, 'min-w-[120px]')}
                            >
                                Tracto
                            </TableHead>
                            <TableHead
                                className={cn(CELDA_CON_BORDE, 'min-w-[180px]')}
                            >
                                Conductor
                            </TableHead>
                            <TableHead
                                className={cn(CELDA_CON_BORDE, 'min-w-[140px]')}
                            >
                                Programación
                            </TableHead>
                            <TableHead
                                className={cn(CELDA_CON_BORDE, 'min-w-[160px]')}
                            >
                                Destino
                            </TableHead>
                            <TableHead
                                className={cn(CELDA_CON_BORDE, 'min-w-[180px]')}
                            >
                                Cliente
                            </TableHead>
                            <TableHead
                                className={cn(CELDA_CON_BORDE, 'min-w-[220px]')}
                            >
                                Observaciones
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filasFiltradas.map((fila) => (
                            <TableRow key={fila.vehiculo_id}>
                                <TableCell
                                    className={cn(
                                        CELDA_CON_BORDE,
                                        'font-medium whitespace-nowrap',
                                    )}
                                >
                                    {fila.placa}
                                    {fila.caja_label && (
                                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                                            ({fila.caja_label.charAt(0)})
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        CELDA_CON_BORDE,
                                        'whitespace-nowrap text-muted-foreground',
                                    )}
                                >
                                    {fila.conductor_nombre ?? '—'}
                                </TableCell>
                                <TableCell
                                    className={cn(CELDA_CON_BORDE, 'p-1')}
                                >
                                    <CeldaEstado
                                        vehiculoId={fila.vehiculo_id}
                                        fecha={fecha}
                                        marca={fila.marca}
                                    />
                                </TableCell>
                                <TableCell
                                    className={cn(CELDA_CON_BORDE, 'p-1')}
                                >
                                    <CeldaDestino
                                        vehiculoId={fila.vehiculo_id}
                                        fecha={fecha}
                                        marca={fila.marca}
                                    />
                                </TableCell>
                                <TableCell
                                    className={cn(CELDA_CON_BORDE, 'p-1')}
                                >
                                    <CeldaCliente
                                        vehiculoId={fila.vehiculo_id}
                                        fecha={fecha}
                                        marca={fila.marca}
                                        clientes={clientes}
                                    />
                                </TableCell>
                                <TableCell
                                    className={cn(CELDA_CON_BORDE, 'p-1')}
                                >
                                    <CeldaObservaciones
                                        vehiculoId={fila.vehiculo_id}
                                        fecha={fecha}
                                        marca={fila.marca}
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

function FiltroBoton({
    activo,
    onClick,
    badge,
    children,
}: {
    activo: boolean;
    onClick: () => void;
    badge?: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-none border px-2.5 py-1 text-xs font-medium transition-colors',
                activo
                    ? 'border-foreground bg-foreground text-background'
                    : 'border-border text-muted-foreground hover:bg-muted',
            )}
        >
            {badge && !activo && (
                <span className={cn('size-2 rounded-full', badge)} />
            )}
            {children}
        </button>
    );
}

function CeldaEstado({
    vehiculoId,
    fecha,
    marca,
}: {
    vehiculoId: number;
    fecha: string;
    marca: ProgramacionFila['marca'];
}) {
    const info = marca ? estadoConfig[marca.estado] : null;

    const marcarComo = (estado: EstadoProgramacion) => {
        router.patch(
            marcar(vehiculoId).url,
            {
                fecha,
                estado,
                observaciones: marca?.observaciones ?? undefined,
            },
            { preserveScroll: true },
        );
    };

    const quitarMarca = () => {
        if (!marca) {
            return;
        }

        router.delete(destroy(marca.programacion_id).url, {
            preserveScroll: true,
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'w-full cursor-pointer px-2 py-1 text-left hover:ring-1 hover:ring-foreground/30 hover:ring-inset',
                )}
            >
                <span
                    className={cn(
                        'text-xs font-semibold',
                        info ? info.badge : 'text-muted-foreground',
                    )}
                >
                    {info ? info.label : 'Sin marcar'}
                </span>
                {marca?.estado_anterior_label && (
                    <span className="block text-[10px] leading-tight text-muted-foreground">
                        antes: {marca.estado_anterior_label}
                        {marca.estado_cambiado_en &&
                            `, hoy ${marca.estado_cambiado_en}`}
                    </span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {(Object.keys(estadoConfig) as EstadoProgramacion[]).map(
                    (estado) => (
                        <DropdownMenuItem
                            key={estado}
                            onSelect={() => marcarComo(estado)}
                        >
                            <span
                                className={cn(
                                    'inline-block size-2.5 rounded-full',
                                    estadoConfig[estado].badge,
                                )}
                            />
                            {estadoConfig[estado].label}
                        </DropdownMenuItem>
                    ),
                )}
                {marca && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={quitarMarca}
                            className="text-muted-foreground"
                        >
                            Quitar marca
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function CeldaDestino({
    vehiculoId,
    fecha,
    marca,
}: {
    vehiculoId: number;
    fecha: string;
    marca: ProgramacionFila['marca'];
}) {
    const [valor, setValor] = useState(marca?.destino ?? '');

    const guardar = () => {
        if (!marca || valor === (marca.destino ?? '')) {
            return;
        }

        router.patch(
            marcar(vehiculoId).url,
            { fecha, estado: marca.estado, destino: valor },
            { preserveScroll: true },
        );
    };

    return (
        <Input
            value={valor}
            onChange={(e) => setValor(e.target.value)}
            onBlur={guardar}
            disabled={!marca}
            placeholder={marca ? 'Ej: Callao' : 'Marca un estado primero'}
            className="h-7 rounded-none border-none text-xs shadow-none focus-visible:ring-1"
        />
    );
}

function CeldaObservaciones({
    vehiculoId,
    fecha,
    marca,
}: {
    vehiculoId: number;
    fecha: string;
    marca: ProgramacionFila['marca'];
}) {
    const [valor, setValor] = useState(marca?.observaciones ?? '');

    const guardar = () => {
        if (!marca || valor === (marca.observaciones ?? '')) {
            return;
        }

        router.patch(
            marcar(vehiculoId).url,
            { fecha, estado: marca.estado, observaciones: valor },
            { preserveScroll: true },
        );
    };

    return (
        <Input
            value={valor}
            onChange={(e) => setValor(e.target.value)}
            onBlur={guardar}
            disabled={!marca}
            placeholder={
                marca ? 'Sin observaciones' : 'Marca un estado primero'
            }
            className="h-7 rounded-none border-none text-xs shadow-none focus-visible:ring-1"
        />
    );
}

/**
 * Solo tiene sentido cuando el estado es «particular»: el cliente final del
 * viaje (Promart, Cerámica San Lorenzo...) no tiene catálogo fijo, así que es
 * texto libre igual que las observaciones.
 */
/**
 * Minsur es el cliente de casi todo (el propio Minsur manda el aviso); el
 * resto son clientes de carga particular. Selector con los clientes ya
 * conocidos + «Otro» para escribir uno nuevo la primera vez que aparece —
 * no hay catálogo administrable aparte, la lista crece con lo ya tipeado.
 */
function CeldaCliente({
    vehiculoId,
    fecha,
    marca,
    clientes,
}: {
    vehiculoId: number;
    fecha: string;
    marca: ProgramacionFila['marca'];
    clientes: string[];
}) {
    const clienteActual = marca?.cliente ?? '';
    const [modoPersonalizado, setModoPersonalizado] = useState(
        clienteActual !== '' && !clientes.includes(clienteActual),
    );
    const [valor, setValor] = useState(clienteActual);

    const guardar = (nuevoValor: string) => {
        if (!marca || nuevoValor === (marca.cliente ?? '')) {
            return;
        }

        router.patch(
            marcar(vehiculoId).url,
            { fecha, estado: marca.estado, cliente: nuevoValor },
            { preserveScroll: true },
        );
    };

    if (!marca) {
        return (
            <Input
                disabled
                placeholder="Marca un estado primero"
                className="h-7 rounded-none border-none text-xs shadow-none"
            />
        );
    }

    if (modoPersonalizado) {
        return (
            <div className="flex items-center">
                <Input
                    value={valor}
                    autoFocus
                    onChange={(e) => setValor(e.target.value)}
                    onBlur={() => guardar(valor)}
                    placeholder="Nombre del cliente"
                    className="h-7 rounded-none border-none text-xs shadow-none focus-visible:ring-1"
                />
                <button
                    type="button"
                    onClick={() => setModoPersonalizado(false)}
                    className="shrink-0 px-1.5 text-muted-foreground hover:text-foreground"
                    title="Elegir de la lista"
                >
                    <List className="size-3.5" />
                </button>
            </div>
        );
    }

    return (
        <Select
            value={clienteActual || CLIENTE_SIN_ELEGIR}
            onValueChange={(value) => {
                if (value === CLIENTE_OTRO) {
                    setModoPersonalizado(true);
                    setValor('');

                    return;
                }

                const nuevoValor = value === CLIENTE_SIN_ELEGIR ? '' : value;
                setValor(nuevoValor);
                guardar(nuevoValor);
            }}
        >
            <SelectTrigger className="h-7 w-full rounded-none border-none text-xs shadow-none focus-visible:ring-1">
                <SelectValue placeholder="Sin cliente" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={CLIENTE_SIN_ELEGIR}>Sin cliente</SelectItem>
                {clientes.map((cliente) => (
                    <SelectItem key={cliente} value={cliente}>
                        {cliente}
                    </SelectItem>
                ))}
                <SelectItem value={CLIENTE_OTRO}>Otro...</SelectItem>
            </SelectContent>
        </Select>
    );
}

/** «5 ago» a partir de una fecha Y-m-d. */
function formatearFecha(fecha: string): string {
    const d = new Date(`${fecha}T00:00:00`);

    return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
}

ProgramacionIndex.layout = {
    breadcrumbs: [{ title: 'Programación', href: programacion.index().url }],
};
