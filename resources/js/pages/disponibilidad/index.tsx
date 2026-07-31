import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    CopyPlus,
    Search,
    Trash2,
    Truck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import disponibilidad from '@/actions/App/Http/Controllers/DisponibilidadController';
import { CeldaEditable } from '@/components/disponibilidad/celda-editable';
import { Button } from '@/components/ui/button';
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
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    DisponibilidadFila,
    DisponibilidadOpciones,
    DisponibilidadResumen,
} from '@/types/fleet';

type Props = {
    fecha: string;
    caja: string | null;
    filas: DisponibilidadFila[];
    resumen: DisponibilidadResumen;
    arrastrables: number;
    opciones: DisponibilidadOpciones;
};

/** Celdas compactas con borde propio: que la grilla se vea, como en el Excel. */
const CABECERA = 'h-7 border px-2 py-1';
const CELDA = 'border px-2 py-1 text-xs';

const TODAS_LAS_CAJAS = 'todas';

type Orden = { columna: string; direccion: 'asc' | 'desc' };

/** De dónde saca cada columna el valor con el que se compara al ordenar. */
const VALOR_COLUMNA: Record<string, (fila: DisponibilidadFila) => string> = {
    unidad: (fila) => fila.tracto.placa,
    carreta: (fila) => fila.carreta?.placa ?? '',
    conductor: (fila) => fila.conductor?.nombre_completo ?? '',
    tipo_carga: (fila) => fila.tipo_carga_label ?? '',
    cliente: (fila) => fila.cliente_label ?? '',
    origen: (fila) => fila.origen ?? '',
    destino: (fila) => fila.destino ?? '',
    paradas: (fila) => fila.proximas_paradas ?? '',
    ubicacion: (fila) => fila.ubicacion ?? '',
    observaciones: (fila) => fila.observaciones ?? '',
    fecha_disponible: (fila) => fila.fecha_disponible ?? '',
};

const desplazarDias = (fecha: string, dias: number): string => {
    const dia = new Date(`${fecha}T00:00:00`);
    dia.setDate(dia.getDate() + dias);

    return dia.toISOString().slice(0, 10);
};

/** Encabezado de columna con orden A-Z / Z-A al hacer click, como en Excel. */
function CabeceraOrdenable({
    columna,
    orden,
    onOrdenar,
    children,
}: {
    columna: string;
    orden: Orden | null;
    onOrdenar: (columna: string) => void;
    children: React.ReactNode;
}) {
    const activa = orden?.columna === columna;

    return (
        <TableHead className={CABECERA}>
            <button
                type="button"
                className="flex items-center gap-1 hover:text-foreground"
                onClick={() => onOrdenar(columna)}
            >
                {children}
                {activa && orden.direccion === 'asc' ? (
                    <ArrowUp className="size-3" />
                ) : activa ? (
                    <ArrowDown className="size-3" />
                ) : (
                    <ArrowUpDown className="size-3 text-muted-foreground/40" />
                )}
            </button>
        </TableHead>
    );
}

export default function DisponibilidadIndex({
    fecha,
    caja,
    filas,
    resumen,
    arrastrables,
    opciones,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const [orden, setOrden] = useState<Orden | null>(null);
    const [busqueda, setBusqueda] = useState('');

    const irA = (parametros: { fecha?: string; caja?: string | null }) =>
        router.get(
            disponibilidad.index().url,
            {
                fecha: parametros.fecha ?? fecha,
                ...(parametros.caja !== undefined
                    ? { caja: parametros.caja }
                    : caja
                      ? { caja }
                      : {}),
            },
            { preserveScroll: true, replace: true },
        );

    const alternarOrden = (columna: string) => {
        setOrden((actual) => {
            if (actual?.columna !== columna) {
                return { columna, direccion: 'asc' };
            }

            return actual.direccion === 'asc'
                ? { columna, direccion: 'desc' }
                : null;
        });
    };

    const filasOrdenadas = useMemo(() => {
        const obtenerValor = orden && VALOR_COLUMNA[orden.columna];

        if (!orden || !obtenerValor) {
            return filas;
        }

        const factor = orden.direccion === 'asc' ? 1 : -1;

        return [...filas].sort(
            (a, b) =>
                factor * obtenerValor(a).localeCompare(obtenerValor(b), 'es'),
        );
    }, [filas, orden]);

    const filasVisibles = useMemo(() => {
        const termino = busqueda.trim().toLowerCase();

        if (!termino) {
            return filasOrdenadas;
        }

        const columnas = Object.values(VALOR_COLUMNA);

        return filasOrdenadas.filter((fila) =>
            columnas.some((obtenerValor) =>
                obtenerValor(fila).toLowerCase().includes(termino),
            ),
        );
    }, [filasOrdenadas, busqueda]);

    const arrastrar = () =>
        router.post(
            disponibilidad.arrastrar().url,
            { fecha },
            { preserveScroll: true },
        );

    const vaciar = (fila: DisponibilidadFila) => {
        if (fila.id === null) {
            return;
        }

        if (
            !confirm(
                `¿Vaciar la carga de ${fila.tracto.placa} en este día? Se borran tipo de carga, cliente, origen, destino y ubicación. Carreta y conductor se mantienen.`,
            )
        ) {
            return;
        }

        router.delete(disponibilidad.destroy(fila.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Disponibilidad" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Disponibilidad
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {resumen.reportadas} de {resumen.total}{' '}
                        {resumen.total === 1
                            ? 'unidad reportada'
                            : 'unidades reportadas'}
                        {resumen.imposibles > 0 &&
                            ` · ${resumen.imposibles} por corregir`}
                        {resumen.improbables > 0 &&
                            ` · ${resumen.improbables} por confirmar`}
                    </p>
                </div>

                {puedeGestionar && (
                    <div className="flex flex-wrap gap-2">
                        {arrastrables > 0 && (
                            <Button variant="outline" onClick={arrastrar}>
                                <CopyPlus className="size-4" />
                                Arrastrar {arrastrables}{' '}
                                {arrastrables === 1 ? 'unidad' : 'unidades'}
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full sm:w-64">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={busqueda}
                        onChange={(evento) => setBusqueda(evento.target.value)}
                        placeholder="Buscar placa, conductor, cliente..."
                        aria-label="Buscar en la tabla"
                        className="h-9 pl-8"
                    />
                </div>

                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Día anterior"
                    onClick={() => irA({ fecha: desplazarDias(fecha, -1) })}
                >
                    <ChevronLeft className="size-4" />
                </Button>

                <Input
                    type="date"
                    value={fecha}
                    aria-label="Fecha"
                    className="w-auto"
                    onChange={(evento) => irA({ fecha: evento.target.value })}
                />

                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Día siguiente"
                    onClick={() => irA({ fecha: desplazarDias(fecha, 1) })}
                >
                    <ChevronRight className="size-4" />
                </Button>

                <Select
                    value={caja ?? TODAS_LAS_CAJAS}
                    onValueChange={(valor) =>
                        irA({
                            caja: valor === TODAS_LAS_CAJAS ? null : valor,
                        })
                    }
                >
                    <SelectTrigger
                        className="h-9 w-auto"
                        aria-label="Filtrar por tipo de caja"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={TODAS_LAS_CAJAS}>
                            Mecánicas y automáticas
                        </SelectItem>
                        {opciones.cajas.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                {opcion.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {filas.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center bg-muted text-muted-foreground">
                        <Truck className="size-7" />
                    </div>
                    <p className="font-medium">No hay tractos activos</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Da de alta al menos un tracto activo para que aparezca
                        acá.
                    </p>
                </div>
            ) : filasVisibles.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center bg-muted text-muted-foreground">
                        <Search className="size-7" />
                    </div>
                    <p className="font-medium">Sin resultados</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Nada coincide con &quot;{busqueda}&quot;.
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <Table className="border-collapse">
                        <TableHeader>
                            <TableRow>
                                <TableHead className={CABECERA}>#</TableHead>
                                <CabeceraOrdenable
                                    columna="unidad"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Unidad
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="carreta"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Carreta
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="conductor"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Conductor
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="tipo_carga"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Tipo de carga
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="cliente"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Cliente
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="origen"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Origen
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="destino"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Destino
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="paradas"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Próximas paradas
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="ubicacion"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Ubicación
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="observaciones"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Observaciones
                                </CabeceraOrdenable>
                                <CabeceraOrdenable
                                    columna="fecha_disponible"
                                    orden={orden}
                                    onOrdenar={alternarOrden}
                                >
                                    Fecha disponible
                                </CabeceraOrdenable>
                                {puedeGestionar && (
                                    <TableHead
                                        className={cn(CABECERA, 'text-right')}
                                    >
                                        Acciones
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {filasVisibles.map((fila, indice) => (
                                <TableRow key={fila.tracto.id}>
                                    <TableCell
                                        className={cn(
                                            CELDA,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {indice + 1}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            CELDA,
                                            'font-medium whitespace-nowrap',
                                        )}
                                    >
                                        {formatearPlaca(fila.tracto.placa)}
                                        {fila.tracto.caja && (
                                            <span
                                                className="ml-1 text-muted-foreground"
                                                title={
                                                    fila.tracto.caja ===
                                                    'automatica'
                                                        ? 'Automática'
                                                        : 'Mecánica'
                                                }
                                            >
                                                (
                                                {fila.tracto.caja ===
                                                'automatica'
                                                    ? 'A'
                                                    : 'M'}
                                                )
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="carreta_id"
                                                valor={
                                                    fila.carreta
                                                        ? String(
                                                              fila.carreta.id,
                                                          )
                                                        : null
                                                }
                                                valorLabel={
                                                    fila.carreta?.placa ?? null
                                                }
                                                origen={
                                                    fila.origenes.carreta_id
                                                }
                                                control={{
                                                    tipo: 'select',
                                                    opciones: opciones.carretas,
                                                    placeholder: 'Sin carreta',
                                                }}
                                            />
                                        ) : (
                                            (fila.carreta?.placa ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="conductor_id"
                                                valor={
                                                    fila.conductor
                                                        ? String(
                                                              fila.conductor.id,
                                                          )
                                                        : null
                                                }
                                                valorLabel={
                                                    fila.conductor
                                                        ?.nombre_completo ??
                                                    null
                                                }
                                                origen={
                                                    fila.origenes.conductor_id
                                                }
                                                control={{
                                                    tipo: 'select',
                                                    opciones:
                                                        opciones.conductores,
                                                    placeholder:
                                                        'Sin conductor',
                                                }}
                                            />
                                        ) : (
                                            (fila.conductor?.nombre_completo ??
                                            '—')
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="tipo_carga"
                                                valor={fila.tipo_carga}
                                                valorLabel={
                                                    fila.tipo_carga_label
                                                }
                                                origen={
                                                    fila.origenes.tipo_carga
                                                }
                                                control={{
                                                    tipo: 'select',
                                                    opciones: opciones.cargas,
                                                    placeholder:
                                                        'Sin registrar',
                                                }}
                                            />
                                        ) : (
                                            (fila.tipo_carga_label ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="cliente"
                                                valor={fila.cliente}
                                                valorLabel={fila.cliente_label}
                                                origen={fila.origenes.cliente}
                                                control={{
                                                    tipo: 'select',
                                                    opciones: opciones.clientes,
                                                    placeholder:
                                                        'Sin registrar',
                                                }}
                                            />
                                        ) : (
                                            (fila.cliente_label ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="origen"
                                                valor={fila.origen}
                                                valorLabel={fila.origen}
                                                origen={fila.origenes.origen}
                                                control={{
                                                    tipo: 'texto',
                                                    placeholder:
                                                        'Sin registrar',
                                                }}
                                            />
                                        ) : (
                                            (fila.origen ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="destino"
                                                valor={fila.destino}
                                                valorLabel={fila.destino}
                                                origen={fila.origenes.destino}
                                                control={{
                                                    tipo: 'texto',
                                                    placeholder:
                                                        'Sin registrar',
                                                }}
                                            />
                                        ) : (
                                            (fila.destino ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell
                                        className={cn(CELDA, 'min-w-40')}
                                    >
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="proximas_paradas"
                                                valor={fila.proximas_paradas}
                                                valorLabel={
                                                    fila.proximas_paradas
                                                }
                                                control={{ tipo: 'texto' }}
                                            />
                                        ) : (
                                            (fila.proximas_paradas ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell className={CELDA}>
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="ubicacion"
                                                valor={fila.ubicacion}
                                                valorLabel={fila.ubicacion}
                                                origen={
                                                    fila.origenes.ubicacion
                                                }
                                                control={{
                                                    tipo: 'texto',
                                                    placeholder:
                                                        'Sin registrar',
                                                }}
                                            />
                                        ) : (
                                            (fila.ubicacion ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell
                                        className={cn(CELDA, 'min-w-40')}
                                    >
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="observaciones"
                                                valor={fila.observaciones}
                                                valorLabel={fila.observaciones}
                                                control={{ tipo: 'texto' }}
                                            />
                                        ) : (
                                            (fila.observaciones ?? '—')
                                        )}
                                    </TableCell>

                                    <TableCell
                                        className={cn(
                                            CELDA,
                                            'whitespace-nowrap',
                                        )}
                                    >
                                        {puedeGestionar ? (
                                            <CeldaEditable
                                                tractoId={fila.tracto.id}
                                                fecha={fecha}
                                                campo="fecha_disponible"
                                                valor={fila.fecha_disponible}
                                                valorLabel={formatearFecha(
                                                    fila.fecha_disponible,
                                                )}
                                                control={{ tipo: 'fecha' }}
                                            />
                                        ) : (
                                            formatearFecha(
                                                fila.fecha_disponible,
                                            )
                                        )}
                                    </TableCell>

                                    {puedeGestionar && (
                                        <TableCell
                                            className={cn(
                                                CELDA,
                                                'text-right whitespace-nowrap',
                                            )}
                                        >
                                            {fila.id !== null && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Vaciar carga de ${fila.tracto.placa}`}
                                                    onClick={() => vaciar(fila)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
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

DisponibilidadIndex.layout = {
    breadcrumbs: [
        { title: 'Disponibilidad', href: disponibilidad.index().url },
    ],
};
