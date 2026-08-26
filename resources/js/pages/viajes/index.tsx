import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Eye,
    FilePenLine,
    RefreshCw,
    Route as RouteIcon,
    Trash2,
    Upload,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { show as mostrarConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { show as mostrarVehiculo } from '@/actions/App/Http/Controllers/VehiculoController';
import viajes, {
    actualizarTipoCarga,
    create,
    resolver,
    store,
} from '@/actions/App/Http/Controllers/ViajeController';
import { DireccionCelda } from '@/components/direccion-celda';
import { FiltroSelect } from '@/components/filtro-select';
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
import { ClienteChip } from '@/components/viajes/cliente-chip';
import { DeleteViajeDialog } from '@/components/viajes/delete-viaje-dialog';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import { ViajeDetalleDialog } from '@/components/viajes/viaje-detalle-dialog';
import { useViajeFiltros } from '@/hooks/use-viaje-filtros';
import type { FiltrosViaje } from '@/hooks/use-viaje-filtros';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, Paginator, ViajeListItem } from '@/types/fleet';

type FilaViaje = {
    viaje: ViajeListItem;
    agrupado: boolean;
    colorGrupo: string | null;
};

/**
 * Colores del borde de grupo, alternados entre grupos consecutivos — no por
 * significado (a diferencia del color de cliente), solo para que dos grupos
 * distintos que caen uno pegado al otro en la tabla (ej. dos placas
 * distintas, mismo día) no se lean como un borde continuo de un solo grupo.
 * Con 2 alcanza: grupos consecutivos nunca repiten color entre sí.
 */
const COLORES_GRUPO = [
    'border-l-primary',
    'border-l-slate-400 dark:border-l-slate-500',
] as const;

/**
 * Una GR no es un viaje: el mismo camión puede salir una vez y llevar carga
 * de dos clientes, cada una con su propia GR (ver `Viaje::claveGrupoViaje`
 * en el backend). Acá solo se cuenta cuántas filas comparten esa clave —
 * si hay más de una, se marcan como agrupadas para que la tabla les ponga
 * un borde compartido en vez de tratarlas como viajes independientes.
 */
function agruparViajes(datos: ViajeListItem[]): FilaViaje[] {
    const conteos = new Map<string, number>();

    for (const viaje of datos) {
        conteos.set(
            viaje.grupo_viaje,
            (conteos.get(viaje.grupo_viaje) ?? 0) + 1,
        );
    }

    let indiceGrupo = -1;
    let claveAnterior: string | null = null;

    return datos.map((viaje) => {
        const agrupado = (conteos.get(viaje.grupo_viaje) ?? 0) > 1;

        if (viaje.grupo_viaje !== claveAnterior) {
            indiceGrupo++;
            claveAnterior = viaje.grupo_viaje;
        }

        return {
            viaje,
            agrupado,
            colorGrupo: agrupado
                ? COLORES_GRUPO[indiceGrupo % COLORES_GRUPO.length]
                : null,
        };
    });
}

type Props = {
    viajes: Paginator<ViajeListItem>;
    filtros: FiltrosViaje;
    /** Viajes sin tracto, conductor, o carreta resueltos contra el padrón. */
    pendientes: number;
    tiposCarga: EnumOption[];
    clientes: EnumOption[];
    ciudadesDestino: EnumOption[];
};

export default function ViajesIndex({
    viajes: paginador,
    filtros,
    pendientes,
    tiposCarga,
    clientes,
    ciudadesDestino,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const { buscar, setBuscar, aplicar } = useViajeFiltros(filtros);
    const filtrosActivos = [
        filtros.cliente,
        filtros.destino_ciudad,
        filtros.tipo_carga,
    ].filter(Boolean).length;
    const [viajeSeleccionado, setViajeSeleccionado] =
        useState<ViajeListItem | null>(null);
    const filas = useMemo(
        () => agruparViajes(paginador.data),
        [paginador.data],
    );

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Viajes" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
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
                activos={filtrosActivos}
                onLimpiar={() =>
                    aplicar({
                        cliente: null,
                        destino_ciudad: null,
                        tipo_carga: null,
                    })
                }
            >
                <FiltroSelect
                    valor={filtros.cliente}
                    onCambio={(cliente) => aplicar({ cliente })}
                    todos="Todos los clientes"
                    etiqueta="Cliente"
                    opciones={clientes}
                />
                <FiltroSelect
                    valor={filtros.destino_ciudad}
                    onCambio={(destino_ciudad) => aplicar({ destino_ciudad })}
                    todos="Todos los destinos"
                    etiqueta="Destino"
                    opciones={ciudadesDestino}
                />
                <FiltroSelect
                    valor={filtros.tipo_carga}
                    onCambio={(tipo_carga) => aplicar({ tipo_carga })}
                    todos="Todos los tipos de carga"
                    etiqueta="Carga"
                    opciones={tiposCarga}
                />
            </FiltrosBarra>

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
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
                    <div className="overflow-x-auto rounded-xl border shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>N° GR</TableHead>
                                    <TableHead>Tracto</TableHead>
                                    <TableHead>Carreta</TableHead>
                                    <TableHead>Conductor</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Origen</TableHead>
                                    <TableHead>Destino</TableHead>
                                    <TableHead>Tipo de carga</TableHead>
                                    <TableHead className="text-right">
                                        Peso
                                    </TableHead>
                                    <TableHead className="w-0" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filas.map(({ viaje, colorGrupo }) => (
                                    <TableRow
                                        key={viaje.id}
                                        className={cn(
                                            'cursor-pointer',
                                            colorGrupo &&
                                                cn('border-l-2', colorGrupo),
                                        )}
                                        onClick={(evento) => {
                                            const objetivo =
                                                evento.target as HTMLElement;

                                            if (
                                                objetivo.closest(
                                                    'a, button, [role="menuitem"]',
                                                )
                                            ) {
                                                return;
                                            }

                                            setViajeSeleccionado(viaje);
                                        }}
                                    >
                                        <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatearFecha(
                                                viaje.fecha_traslado,
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-xs tabular-nums">
                                            {viaje.numero_gr}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            <PlacaCelda
                                                placa={viaje.placa_tracto}
                                                vehiculoId={viaje.tracto_id}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
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
                                        <TableCell className="whitespace-nowrap">
                                            <NombreCelda
                                                nombre={viaje.conductor_nombre}
                                                conductorId={viaje.conductor_id}
                                            />
                                        </TableCell>
                                        <TableCell className="max-w-[200px]">
                                            <ClienteChip
                                                cliente={viaje.cliente}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <DireccionCelda
                                                ciudad={viaje.origen_ciudad}
                                                direccion={viaje.origen}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <DireccionCelda
                                                ciudad={viaje.destino_ciudad}
                                                direccion={viaje.destino}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <TipoCargaCelda
                                                viajeId={viaje.id}
                                                valor={viaje.tipo_carga}
                                                label={viaje.tipo_carga_label}
                                                opciones={tiposCarga}
                                                editable={puedeGestionar}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                            {viaje.peso.toLocaleString('es-PE')}{' '}
                                            {viaje.unidad_peso}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                <DocumentoVisorDialog
                                                    url={
                                                        viaje.archivo_url ?? ''
                                                    }
                                                    esPdf
                                                    titulo={`GR ${viaje.numero_gr}`}
                                                    detalle={`${viaje.cliente} · ${formatearFecha(viaje.fecha_traslado)}`}
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled={
                                                                !viaje.archivo_url
                                                            }
                                                            className="size-8 text-muted-foreground"
                                                            aria-label="Vista rápida de la GR"
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>
                                                    }
                                                />
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

            <ViajeDetalleDialog
                viaje={viajeSeleccionado}
                onOpenChange={(abierto) => {
                    if (!abierto) {
                        setViajeSeleccionado(null);
                    }
                }}
            />
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
 * La GR no trae qué tipo de carga es —eso solo lo sabe quien clasifica el
 * archivo a mano—, así que se corrige acá mismo con un desplegable en vez de
 * mandar a un formulario aparte. El badge queda neutro (no el color del
 * cliente) para no competir con el chip de `ClienteChip`, que es la señal
 * principal de la fila.
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
        return <TipoCargaBadge valor={valor} label={label} />;
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
                className="cursor-pointer rounded-md disabled:cursor-wait disabled:opacity-60"
            >
                <TipoCargaBadge
                    valor={valor}
                    label={label}
                    className="hover:bg-accent hover:text-accent-foreground"
                />
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
            <span className="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white">
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
