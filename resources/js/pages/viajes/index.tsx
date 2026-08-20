import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronDown,
    FilePenLine,
    FileText,
    RefreshCw,
    Route as RouteIcon,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { show as mostrarConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { show as mostrarVehiculo } from '@/actions/App/Http/Controllers/VehiculoController';
import viajes, {
    actualizarTipoCarga,
    create,
    resolver,
    store,
} from '@/actions/App/Http/Controllers/ViajeController';
import { DestinoCelda } from '@/components/destino-celda';
import { FiltrosBarra } from '@/components/filtros-barra';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { DeleteViajeDialog } from '@/components/viajes/delete-viaje-dialog';
import { useViajeFiltros } from '@/hooks/use-viaje-filtros';
import type { FiltrosViaje } from '@/hooks/use-viaje-filtros';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, Paginator, ViajeListItem } from '@/types/fleet';

/** Celdas compactas con borde propio: misma grilla que la tabla de disponibilidad. */
const CABECERA = 'h-7 border px-2 py-1';
const CELDA = 'border px-2 py-1 text-xs';

type Props = {
    viajes: Paginator<ViajeListItem>;
    filtros: FiltrosViaje;
    /** Viajes sin tracto, conductor, o carreta resueltos contra el padrón. */
    pendientes: number;
    tiposCarga: EnumOption[];
};

export default function ViajesIndex({
    viajes: paginador,
    filtros,
    pendientes,
    tiposCarga,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const { buscar, setBuscar } = useViajeFiltros(filtros);

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
                        {paginador.total === 1
                            ? 'viaje registrado a partir de las GR emitidas'
                            : 'viajes registrados a partir de las GR emitidas'}
                    </p>
                </div>

                {puedeGestionar && (
                    <div className="flex flex-wrap items-center gap-2">
                        {pendientes > 0 && (
                            <ReintentarCoincidencias pendientes={pendientes} />
                        )}
                        <Button asChild variant="outline">
                            <Link href={create()}>
                                <FilePenLine className="size-4" />
                                Agregar manualmente
                            </Link>
                        </Button>
                        <SubirGuias />
                    </div>
                )}
            </div>

            <FiltrosBarra
                buscar={buscar}
                onBuscar={setBuscar}
                placeholder="Buscar por placa, cliente, conductor, destino o N° de GR..."
                etiquetaBusqueda="Buscar viajes"
                activos={0}
                onLimpiar={() => {}}
            >
                {null}
            </FiltrosBarra>

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-none bg-muted text-muted-foreground">
                        <RouteIcon className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron viajes</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        {filtros.buscar
                            ? 'Ajusta la búsqueda.'
                            : puedeGestionar
                              ? 'Sube tus primeras GR para empezar el historial.'
                              : 'Todavía no se ha subido ninguna GR.'}
                    </p>
                </div>
            ) : (
                <>
                    <div className="overflow-x-auto border">
                        <Table className="border-collapse">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className={CABECERA}>
                                        Fecha
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        N° GR
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        Tracto
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        Carreta
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        Conductor
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        Cliente
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        Destino
                                    </TableHead>
                                    <TableHead className={CABECERA}>
                                        Tipo de carga
                                    </TableHead>
                                    <TableHead
                                        className={cn(CABECERA, 'text-right')}
                                    >
                                        Peso
                                    </TableHead>
                                    <TableHead
                                        className={cn(CABECERA, 'w-0')}
                                    />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((viaje) => (
                                    <TableRow key={viaje.id}>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'whitespace-nowrap text-muted-foreground tabular-nums',
                                            )}
                                        >
                                            {formatearFecha(
                                                viaje.fecha_traslado,
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'font-medium whitespace-nowrap tabular-nums',
                                            )}
                                        >
                                            {viaje.numero_gr}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'whitespace-nowrap',
                                            )}
                                        >
                                            <PlacaCelda
                                                placa={viaje.placa_tracto}
                                                vehiculoId={viaje.tracto_id}
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'whitespace-nowrap',
                                            )}
                                        >
                                            {viaje.placa_carreta ? (
                                                <PlacaCelda
                                                    placa={viaje.placa_carreta}
                                                    vehiculoId={
                                                        viaje.carreta_id
                                                    }
                                                />
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'whitespace-nowrap',
                                            )}
                                        >
                                            <NombreCelda
                                                nombre={viaje.conductor_nombre}
                                                conductorId={viaje.conductor_id}
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'max-w-[160px]',
                                            )}
                                        >
                                            <ClienteCelda
                                                cliente={viaje.cliente}
                                            />
                                        </TableCell>
                                        <TableCell className={CELDA}>
                                            <DestinoCelda
                                                region={viaje.destino_region}
                                                direccion={viaje.destino}
                                            />
                                        </TableCell>
                                        <TableCell className={CELDA}>
                                            <TipoCargaCelda
                                                viajeId={viaje.id}
                                                valor={viaje.tipo_carga}
                                                label={viaje.tipo_carga_label}
                                                opciones={tiposCarga}
                                                editable={puedeGestionar}
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'text-right whitespace-nowrap tabular-nums',
                                            )}
                                        >
                                            {viaje.peso.toLocaleString('es-PE')}{' '}
                                            {viaje.unidad_peso}
                                        </TableCell>
                                        <TableCell className={CELDA}>
                                            <div className="flex items-center justify-end gap-1">
                                                {viaje.archivo_url && (
                                                    <DocumentoVisorDialog
                                                        url={viaje.archivo_url}
                                                        esPdf
                                                        titulo={`GR ${viaje.numero_gr}`}
                                                        detalle={`${viaje.cliente} · ${formatearFecha(viaje.fecha_traslado)}`}
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8"
                                                                aria-label="Ver GR"
                                                            >
                                                                <FileText className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                                {puedeGestionar && (
                                                    <DeleteViajeDialog
                                                        viaje={viaje}
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                                aria-label="Eliminar viaje"
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}

/**
 * Placa tal como vino en la GR. Si matcheó contra el padrón de vehículos,
 * enlaza a su ficha; si no, se marca en ámbar: mismo lenguaje visual que el
 * resto de la app para «esto necesita que alguien lo revise».
 */
function PlacaCelda({
    placa,
    vehiculoId,
}: {
    placa: string;
    vehiculoId: number | null;
}) {
    if (vehiculoId !== null) {
        return (
            <Link
                href={mostrarVehiculo(vehiculoId)}
                className="hover:underline"
            >
                {formatearPlaca(placa)}
            </Link>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger className="inline-flex cursor-help items-center gap-1 text-amber-700 dark:text-amber-500">
                    <AlertTriangle className="size-3.5" />
                    {formatearPlaca(placa)}
                </TooltipTrigger>
                <TooltipContent>
                    No se encontró esta placa en el padrón de vehículos.
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

function NombreCelda({
    nombre,
    conductorId,
}: {
    nombre: string;
    conductorId: number | null;
}) {
    if (conductorId !== null) {
        return (
            <Link
                href={mostrarConductor(conductorId)}
                className="hover:underline"
            >
                {nombre}
            </Link>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger className="inline-flex cursor-help items-center gap-1 text-amber-700 dark:text-amber-500">
                    <AlertTriangle className="size-3.5" />
                    {nombre}
                </TooltipTrigger>
                <TooltipContent>
                    No se encontró este DNI en el padrón de conductores.
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/**
 * Truncado para no comerse el ancho de la tabla con razones sociales largas;
 * el nombre completo aparece al pasar el mouse.
 */
function ClienteCelda({ cliente }: { cliente: string }) {
    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger className="block max-w-full cursor-help truncate text-left">
                    {cliente}
                </TooltipTrigger>
                <TooltipContent>{cliente}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/**
 * La GR no trae qué tipo de carga es —eso solo lo sabe quien clasifica el
 * archivo a mano—, así que se corrige acá mismo con un desplegable en vez de
 * mandar a un formulario aparte.
 */
function TipoCargaCelda({
    viajeId,
    valor,
    label,
    opciones,
    editable,
}: {
    viajeId: number;
    valor: string;
    label: string;
    opciones: EnumOption[];
    editable: boolean;
}) {
    const [guardando, setGuardando] = useState(false);

    if (!editable) {
        return <span className="whitespace-nowrap">{label}</span>;
    }

    const seleccionar = (nuevoValor: string) => {
        if (nuevoValor === valor) {
            return;
        }

        setGuardando(true);

        router.patch(
            actualizarTipoCarga(viajeId).url,
            { tipo_carga: nuevoValor },
            { preserveScroll: true, onFinish: () => setGuardando(false) },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                disabled={guardando}
                className="inline-flex cursor-pointer items-center gap-1 whitespace-nowrap hover:underline disabled:cursor-wait disabled:opacity-60"
            >
                {label}
                <ChevronDown className="size-3.5 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {opciones.map((opcion) => (
                    <DropdownMenuItem
                        key={opcion.value}
                        onSelect={() => seleccionar(opcion.value)}
                    >
                        {opcion.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Vuelve a intentar resolver tracto/carreta/conductor contra el padrón de
 * hoy. Existe porque la GR suele subirse antes de que la unidad o el
 * conductor estén cargados: crearlos después no actualiza solo lo ya
 * importado, hay que pedirlo.
 */
function ReintentarCoincidencias({ pendientes }: { pendientes: number }) {
    const [procesando, setProcesando] = useState(false);

    const reintentar = () => {
        setProcesando(true);

        router.post(
            resolver().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcesando(false),
            },
        );
    };

    return (
        <Button variant="outline" onClick={reintentar} disabled={procesando}>
            <RefreshCw
                className={`size-4 ${procesando ? 'animate-spin' : ''}`}
            />
            Reintentar coincidencias
            <span className="ml-1 inline-flex min-w-5 items-center justify-center rounded-none bg-amber-500 px-1.5 text-xs font-semibold text-white">
                {pendientes}
            </span>
        </Button>
    );
}

/**
 * Selector de archivos nativo, oculto tras el botón. Sube y sube de una: no
 * hace falta un diálogo con más campos porque todo sale del PDF.
 */
function SubirGuias() {
    const fileInput = useRef<HTMLInputElement>(null);
    const [subiendo, setSubiendo] = useState(false);

    const seleccionar = (evento: React.ChangeEvent<HTMLInputElement>) => {
        const archivos = Array.from(evento.target.files ?? []);

        if (archivos.length === 0) {
            return;
        }

        setSubiendo(true);

        router.post(
            store().url,
            { archivos },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setSubiendo(false);

                    if (fileInput.current) {
                        fileInput.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <>
            <input
                ref={fileInput}
                type="file"
                accept="application/pdf"
                multiple
                className="hidden"
                onChange={seleccionar}
            />
            <Button
                onClick={() => fileInput.current?.click()}
                disabled={subiendo}
            >
                <Upload className="size-4" />
                {subiendo ? 'Subiendo...' : 'Subir GR'}
            </Button>
        </>
    );
}

function Paginacion({ paginador }: { paginador: Paginator<ViajeListItem> }) {
    if (paginador.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
            <p className="text-sm text-muted-foreground">
                Mostrando {paginador.from}–{paginador.to} de {paginador.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {paginador.links.map((link, indice) => (
                    <Button
                        key={indice}
                        asChild={!!link.url}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}

ViajesIndex.layout = {
    breadcrumbs: [{ title: 'Viajes', href: viajes.index().url }],
};
