import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ChevronRight,
    CircleCheck,
    CircleDot,
    ClipboardPlus,
    Clock,
    Gauge,
    Package,
    Wrench,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import {
    MarcaLogo,
    resumenNiveles,
} from '@/components/mantenimiento/marca-logo';
import {
    barraEstado,
    colorEstado,
    ordenEstados,
} from '@/components/mantenimiento/marca-tarjeta';
import type { ConteoEstados } from '@/components/mantenimiento/marca-tarjeta';
import { RegistrarKmDialog } from '@/components/mantenimiento/registrar-km-dialog';
import { Placa } from '@/components/placa';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as verEquipamiento } from '@/routes/mantenimiento/equipamiento';
import { index as equipamientoInicio } from '@/routes/mantenimiento/equipamiento';
import { show as verLlantas } from '@/routes/mantenimiento/llantas';
import { index as llantasInicio } from '@/routes/mantenimiento/llantas';
import {
    create as crearOrden,
    edit as abrirOrden,
} from '@/routes/mantenimiento/ordenes';
import type {
    MarcaMantenimiento,
    ResumenMantenimiento,
    TractoKm,
} from '@/types/mantenimiento';

type Lista<T> = { total: number; unidades: T[] };

type OrdenAbierta = {
    id: number;
    numero: string;
    vehiculo_id: number;
    placa: string;
    tipo: string;
    servicio: string | null;
    estado: string;
    fecha: string;
    faltan: number;
};

type Preventivo = {
    id: number;
    placa: string;
    marca: string | null;
    /** El nivel que toca: X, S, M o L. */
    servicio: string;
    faltan_km: number | null;
    vence_el: string | null;
    /** Materiales del servicio que hoy no alcanzan en el almacén. */
    faltan_en_stock: number;
};

type Props = {
    /** El semáforo de toda la flota con plan. */
    resumen: ResumenMantenimiento;
    marcas: (MarcaMantenimiento & { conteo: ConteoEstados })[];
    /** Los tractos con plan. */
    unidades: number;
    /** Cuántos tienen el último km viejo. */
    desactualizados: number;
    tractos: TractoKm[];
    /** Las unidades que están en taller ahora. */
    ordenes: OrdenAbierta[];
    vencidos: Lista<Preventivo>;
    proximos: Lista<Preventivo>;
    llantas: Lista<{
        id: number;
        placa: string;
        cambiar: number;
        revisar: number;
    }>;
    equipamiento: Lista<{
        id: number;
        placa: string;
        faltan: number;
        vencidos: number;
    }>;
    /** Tractos con plan a los que nunca se les anotó el km. */
    sin_km: Lista<{ id: number; placa: string }>;
};

const km = (numero: number) => Math.abs(numero).toLocaleString('es-PE');

const plural = (cantidad: number, uno: string, varios: string) =>
    `${cantidad} ${cantidad === 1 ? uno : varios}`;

/**
 * La portada de mantenimiento es la cola de trabajo del taller: qué unidad
 * está adentro, a cuál se le venció el preventivo, cuál viene y qué llantas
 * o equipamiento hay que atender. Cada fila lleva a donde se resuelve.
 *
 * El semáforo de la flota y las marcas quedan al costado, como contexto:
 * antes ocupaban toda la pantalla con números que casi siempre eran cero.
 */
export default function MantenimientoIndex({
    resumen,
    marcas,
    unidades,
    desactualizados,
    tractos,
    ordenes,
    vencidos,
    proximos,
    llantas,
    equipamiento,
    sin_km,
}: Props) {
    const { puede } = usePermisos();
    const puedeKm = puede('mantenimiento.registrar_km');
    const puedeOrdenes = puede('mantenimiento.gestionar');
    /** `true`: abierto sin unidad elegida; un número: abierto con esa unidad. */
    const [kmDe, setKmDe] = useState<number | true | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
        ],
    });

    // Lo que no tiene nada pendiente no ocupa una tarjeta vacía: se nombra
    // en una sola línea al final de la cola.
    const alDia = [
        ordenes.length === 0 && 'ninguna unidad en taller',
        vencidos.total === 0 && 'sin preventivos vencidos',
        proximos.total === 0 && 'ningún preventivo próximo',
        llantas.total === 0 && 'sin llantas por cambiar',
        equipamiento.total === 0 && 'equipamiento completo',
    ].filter((texto): texto is string => typeof texto === 'string');

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Mantenimiento" />

            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <EncabezadoPantalla
                    titulo="Mantenimiento"
                    descripcion="Lo que el taller tiene que atender hoy y lo que viene."
                    icono={Wrench}
                />
                <div className="flex gap-2 max-md:hidden">
                    {puedeOrdenes && (
                        <Button variant="outline" asChild>
                            <Link href={crearOrden()}>
                                <ClipboardPlus className="size-4" />
                                Nueva orden
                            </Link>
                        </Button>
                    )}
                    {puedeKm && (
                        <Button onClick={() => setKmDe(true)}>
                            <Gauge className="size-4" />
                            Registrar km
                        </Button>
                    )}
                </div>
            </div>

            {/* Las tres pantallas del módulo, con lo que cada una tiene
                pendiente. */}
            <nav
                aria-label="Secciones de mantenimiento"
                className="flex gap-2 overflow-x-auto pb-1"
            >
                <Seccion
                    href={mantenimiento.preventivo().url}
                    icono={<Wrench />}
                    titulo="Preventivo"
                    pendientes={vencidos.total}
                />
                <Seccion
                    href={llantasInicio().url}
                    icono={<CircleDot />}
                    titulo="Llantas"
                    pendientes={llantas.total}
                />
                <Seccion
                    href={equipamientoInicio().url}
                    icono={<Package />}
                    titulo="Equipamiento"
                    pendientes={equipamiento.total}
                />
                {puedeOrdenes && (
                    <Seccion
                        href={crearOrden().url}
                        icono={<ClipboardPlus />}
                        titulo="Nueva orden"
                        className="md:hidden"
                    />
                )}
            </nav>

            {sin_km.total > 0 && (
                <section className="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                    <div className="flex flex-col gap-1">
                        <h2 className="font-semibold text-amber-900 dark:text-amber-200">
                            {sin_km.total === unidades
                                ? 'Ningún tracto tiene su km anotado todavía'
                                : `${plural(sin_km.total, 'tracto', 'tractos')} sin km anotado`}
                        </h2>
                        <p className="text-sm text-amber-800 dark:text-amber-300">
                            Sin el km no se puede saber qué preventivo le toca.
                            {puedeKm &&
                                ' Toca una placa para anotarlo cuando la unidad esté en base.'}
                        </p>
                    </div>
                    <ul className="flex flex-wrap gap-2">
                        {sin_km.unidades.map((unidad) => (
                            <li key={unidad.id}>
                                {puedeKm ? (
                                    <button
                                        type="button"
                                        onClick={() => setKmDe(unidad.id)}
                                        className="flex min-h-11 items-center rounded-lg px-1 transition-opacity hover:opacity-80"
                                        aria-label={`Anotar el km de ${unidad.placa}`}
                                    >
                                        <Placa placa={unidad.placa} />
                                    </button>
                                ) : (
                                    <Placa placa={unidad.placa} />
                                )}
                            </li>
                        ))}
                        {sin_km.total > sin_km.unidades.length && (
                            <li>
                                <Link
                                    href={mantenimiento.preventivo({
                                        query: { estado: 'sin_datos' },
                                    })}
                                    className="flex min-h-11 items-center gap-1 px-2 text-sm font-medium text-amber-900 underline-offset-2 hover:underline dark:text-amber-200"
                                >
                                    y {sin_km.total - sin_km.unidades.length}{' '}
                                    más
                                    <ChevronRight className="size-4" />
                                </Link>
                            </li>
                        )}
                    </ul>
                </section>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-6 lg:col-span-2">
                    {ordenes.length > 0 && (
                        <Cola
                            titulo="En taller ahora"
                            cantidad={ordenes.length}
                        >
                            {ordenes.map((orden) => (
                                <Fila
                                    key={orden.id}
                                    href={abrirOrden(orden.id).url}
                                    placa={orden.placa}
                                    titulo={`${orden.numero} · ${orden.tipo}${orden.servicio ? ` ${orden.servicio}` : ''}`}
                                    detalle={`${orden.estado} · desde ${formatearFecha(orden.fecha)}`}
                                    alerta={
                                        orden.faltan > 0
                                            ? orden.faltan === 1
                                                ? 'Falta que salga 1 material del almacén'
                                                : `Falta que salgan ${orden.faltan} materiales del almacén`
                                            : undefined
                                    }
                                />
                            ))}
                        </Cola>
                    )}

                    {vencidos.total > 0 && (
                        <Cola
                            titulo="Preventivo vencido"
                            cantidad={vencidos.total}
                            tono="mal"
                            verTodos={
                                vencidos.total > vencidos.unidades.length
                                    ? mantenimiento.preventivo({
                                          query: { estado: 'vencido' },
                                      }).url
                                    : undefined
                            }
                        >
                            {vencidos.unidades.map((unidad) => (
                                <Fila
                                    key={unidad.id}
                                    href={mantenimiento.show(unidad.id).url}
                                    placa={unidad.placa}
                                    titulo={`Preventivo ${unidad.servicio}`}
                                    detalle={
                                        unidad.faltan_km !== null &&
                                        unidad.faltan_km < 0
                                            ? `Vencido por ${km(unidad.faltan_km)} km`
                                            : `Venció el ${formatearFecha(unidad.vence_el)}`
                                    }
                                    alerta={stock(unidad.faltan_en_stock)}
                                    accion={
                                        puedeOrdenes ? (
                                            <Button size="sm" asChild>
                                                <Link
                                                    href={crearOrden({
                                                        query: {
                                                            vehiculo: unidad.id,
                                                        },
                                                    })}
                                                >
                                                    Abrir orden
                                                </Link>
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            ))}
                        </Cola>
                    )}

                    {proximos.total > 0 && (
                        <Cola
                            titulo="Viene pronto"
                            cantidad={proximos.total}
                            tono="aviso"
                            verTodos={
                                proximos.total > proximos.unidades.length
                                    ? mantenimiento.preventivo({
                                          query: { estado: 'proximo' },
                                      }).url
                                    : undefined
                            }
                        >
                            {proximos.unidades.map((unidad) => (
                                <Fila
                                    key={unidad.id}
                                    href={mantenimiento.show(unidad.id).url}
                                    placa={unidad.placa}
                                    titulo={`Preventivo ${unidad.servicio}`}
                                    detalle={
                                        unidad.faltan_km !== null
                                            ? `Faltan ${km(unidad.faltan_km)} km`
                                            : `Vence el ${formatearFecha(unidad.vence_el)}`
                                    }
                                    alerta={stock(unidad.faltan_en_stock)}
                                />
                            ))}
                        </Cola>
                    )}

                    {llantas.total > 0 && (
                        <Cola
                            titulo="Llantas por atender"
                            cantidad={llantas.total}
                            tono="aviso"
                            verTodos={
                                llantas.total > llantas.unidades.length
                                    ? llantasInicio().url
                                    : undefined
                            }
                        >
                            {llantas.unidades.map((unidad) => (
                                <Fila
                                    key={unidad.id}
                                    href={verLlantas(unidad.id).url}
                                    placa={unidad.placa}
                                    titulo={
                                        unidad.cambiar > 0
                                            ? `${plural(unidad.cambiar, 'llanta', 'llantas')} por cambiar`
                                            : `${plural(unidad.revisar, 'llanta', 'llantas')} por revisar`
                                    }
                                    detalle={
                                        unidad.cambiar > 0 && unidad.revisar > 0
                                            ? `y ${unidad.revisar} por revisar`
                                            : undefined
                                    }
                                />
                            ))}
                        </Cola>
                    )}

                    {equipamiento.total > 0 && (
                        <Cola
                            titulo="Equipamiento incompleto"
                            cantidad={equipamiento.total}
                            tono="aviso"
                            verTodos={
                                equipamiento.total >
                                equipamiento.unidades.length
                                    ? equipamientoInicio().url
                                    : undefined
                            }
                        >
                            {equipamiento.unidades.map((unidad) => (
                                <Fila
                                    key={unidad.id}
                                    href={verEquipamiento(unidad.id).url}
                                    placa={unidad.placa}
                                    titulo={[
                                        unidad.faltan > 0 &&
                                            (unidad.faltan === 1
                                                ? 'Falta 1 pieza'
                                                : `Faltan ${unidad.faltan} piezas`),
                                        unidad.vencidos > 0 &&
                                            plural(
                                                unidad.vencidos,
                                                'vencido',
                                                'vencidos',
                                            ),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                />
                            ))}
                        </Cola>
                    )}

                    {alDia.length > 0 && (
                        <p className="flex items-start gap-2 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                            <span>
                                <span className="font-medium text-foreground">
                                    Al día:
                                </span>{' '}
                                {alDia.join(' · ')}.
                            </span>
                        </p>
                    )}
                </div>

                {/* El contexto: cómo está la flota frente a su plan. */}
                <aside className="flex flex-col gap-4 rounded-xl border bg-card p-4">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-sm font-semibold">
                            La flota frente a su plan
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {plural(unidades, 'tracto', 'tractos')} con plan
                            preventivo
                        </p>
                    </div>

                    <BarraEstados
                        conteo={Object.fromEntries(
                            resumen.map((item) => [item.estado, item.cantidad]),
                        )}
                        total={unidades}
                        className="h-3"
                    />
                    <ul className="grid grid-cols-2 gap-x-3 gap-y-1">
                        {resumen.map((item) => (
                            <li key={item.estado}>
                                <Link
                                    href={mantenimiento.preventivo({
                                        query: { estado: item.estado },
                                    })}
                                    className="flex min-h-9 items-center gap-2 rounded-md px-1 text-sm hover:bg-muted"
                                >
                                    <span
                                        className={cn(
                                            'size-2.5 rounded-full',
                                            barraEstado[item.estado],
                                        )}
                                    />
                                    <span className="flex-1 text-muted-foreground">
                                        {item.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'font-semibold tabular-nums',
                                            colorEstado[item.estado],
                                        )}
                                    >
                                        {item.cantidad}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>

                    {desactualizados > 0 && (
                        <Link
                            href={mantenimiento.preventivo({
                                query: { desactualizados: 1 },
                            })}
                            className="flex min-h-9 items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-2 text-sm text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            <Clock className="size-4 shrink-0" />
                            <span className="flex-1">
                                {plural(
                                    desactualizados,
                                    'unidad con el km viejo',
                                    'unidades con el km viejo',
                                )}
                            </span>
                            <ChevronRight className="size-4" />
                        </Link>
                    )}

                    <ul className="flex flex-col border-t pt-2">
                        {marcas.map((marca) => {
                            const total = ordenEstados.reduce(
                                (suma, estado) =>
                                    suma + (marca.conteo[estado] ?? 0),
                                0,
                            );

                            return (
                                <li key={marca.id}>
                                    <Link
                                        href={mantenimiento.preventivo({
                                            query: { marca: marca.marca },
                                        })}
                                        className="flex min-h-12 items-center gap-3 rounded-md px-1 hover:bg-muted"
                                    >
                                        <MarcaLogo
                                            marca={marca.marca}
                                            logoUrl={marca.logo_url}
                                            className="h-8 w-11 p-1"
                                        />
                                        <span className="flex min-w-0 flex-1 flex-col gap-1">
                                            <span className="flex items-baseline justify-between gap-2 text-sm">
                                                <span className="truncate font-medium">
                                                    {marca.marca}
                                                </span>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {total}
                                                </span>
                                            </span>
                                            <BarraEstados
                                                conteo={marca.conteo}
                                                total={total}
                                                className="h-1.5"
                                            />
                                            <span className="truncate text-[11px] text-muted-foreground">
                                                {resumenNiveles(marca.niveles)}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </aside>
            </div>

            {puedeKm && (
                <>
                    <RegistrarKmDialog
                        key={kmDe === null ? 'cerrado' : String(kmDe)}
                        tractos={tractos}
                        vehiculoInicial={
                            typeof kmDe === 'number' ? kmDe : undefined
                        }
                        abierto={kmDe !== null}
                        onAbiertoChange={(abierto) => !abierto && setKmDe(null)}
                    />
                    <AccionPrincipalMovil
                        onClick={() => setKmDe(true)}
                        icono={<Gauge />}
                    >
                        Registrar km
                    </AccionPrincipalMovil>
                </>
            )}
        </div>
    );
}

const stock = (faltan: number): string | undefined =>
    faltan === 0
        ? undefined
        : faltan === 1
          ? '1 material del servicio no alcanza en el almacén'
          : `${faltan} materiales del servicio no alcanzan en el almacén`;

/** Una pantalla del módulo, con cuántas unidades tiene por atender. */
function Seccion({
    href,
    icono,
    titulo,
    pendientes = 0,
    className,
}: {
    href: string;
    icono: ReactNode;
    titulo: string;
    pendientes?: number;
    className?: string;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'flex min-h-11 shrink-0 items-center gap-2 rounded-full border bg-card px-4 text-sm font-medium transition-colors hover:bg-accent/50 [&_svg]:size-4 [&_svg]:text-muted-foreground',
                className,
            )}
        >
            {icono}
            {titulo}
            {pendientes > 0 && (
                <span className="rounded-full bg-red-100 px-1.5 text-xs font-semibold text-red-800 tabular-nums dark:bg-red-950 dark:text-red-200">
                    {pendientes}
                </span>
            )}
        </Link>
    );
}

/** Una lista de la cola de trabajo: su título, cuántas son y las filas. */
function Cola({
    titulo,
    cantidad,
    tono = 'neutro',
    verTodos,
    children,
}: {
    titulo: string;
    cantidad: number;
    tono?: 'mal' | 'aviso' | 'neutro';
    /** Cuando hay más de las que se muestran: a dónde ir a verlas. */
    verTodos?: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h2 className="flex items-center gap-2 text-sm font-semibold">
                {titulo}
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-xs tabular-nums',
                        tono === 'mal' &&
                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
                        tono === 'aviso' &&
                            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                        tono === 'neutro' && 'bg-muted text-muted-foreground',
                    )}
                >
                    {cantidad}
                </span>
            </h2>
            <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                {children}
            </ul>
            {verTodos && (
                <Link
                    href={verTodos}
                    className="flex min-h-9 items-center gap-1 self-start text-sm font-medium text-primary underline-offset-2 hover:underline"
                >
                    Ver las {cantidad}
                    <ChevronRight className="size-4" />
                </Link>
            )}
        </section>
    );
}

/** Una unidad de la cola: la placa primero, qué le pasa y a dónde lleva. */
function Fila({
    href,
    placa,
    titulo,
    detalle,
    alerta,
    accion,
}: {
    href: string;
    placa: string;
    titulo: string;
    detalle?: string;
    /** Lo que puede frenar el trabajo (materiales que faltan). */
    alerta?: string;
    accion?: ReactNode;
}) {
    return (
        <li className="flex items-center gap-2 pr-3">
            <Link
                href={href}
                className="flex min-h-16 min-w-0 flex-1 items-center gap-3 p-3 transition-colors hover:bg-accent/50"
            >
                <Placa placa={placa} />
                <span className="flex min-w-0 flex-1 flex-col text-sm">
                    <span className="font-medium">{titulo}</span>
                    {detalle && (
                        <span className="text-xs text-muted-foreground">
                            {detalle}
                        </span>
                    )}
                    {alerta && (
                        <span className="text-xs font-medium text-red-700 dark:text-red-300">
                            {alerta}
                        </span>
                    )}
                </span>
                {!accion && (
                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                )}
            </Link>
            {accion}
        </li>
    );
}

/** El semáforo como una sola barra repartida por estado. */
function BarraEstados({
    conteo,
    total,
    className,
}: {
    conteo: Partial<ConteoEstados>;
    total: number;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex w-full overflow-hidden rounded-full bg-muted',
                className,
            )}
            aria-hidden
        >
            {total > 0 &&
                ordenEstados.map((estado) => {
                    const cantidad = conteo[estado] ?? 0;

                    return cantidad > 0 ? (
                        <span
                            key={estado}
                            className={barraEstado[estado]}
                            style={{ width: `${(cantidad / total) * 100}%` }}
                        />
                    ) : null;
                })}
        </span>
    );
}
