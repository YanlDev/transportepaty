import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    CircleCheck,
    FileWarning,
    Hourglass,
    Landmark,
} from 'lucide-react';
import { useRef, useState } from 'react';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import detracciones from '@/actions/App/Http/Controllers/DetraccionController';
import reporteRutas from '@/actions/App/Http/Controllers/ReporteCobranzaController';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';

type Monto = { simbolo: string; monto: number };

type ClienteDeuda = {
    cliente: string;
    /** El RUC (o el nombre) con que se filtra en Contabilidad. */
    filtro: string | null;
    /** Cuánto pesa frente al que más debe, de 0 a 1. */
    parte: number;
    facturas: number;
    montos: Monto[];
    vencido: Monto[];
    dias_vencida: number;
    detalle: {
        numero: string;
        fecha: string;
        simbolo: string;
        saldo: number;
        /** También cubre viajes de otro mes: el saldo es solo su parte. */
        repartida: boolean;
        parcial: boolean;
        vence: string;
        dias_vencida: number | null;
    }[];
};

type ClientePorFacturar = {
    cliente: string;
    filtro: string | null;
    guias: number;
    mas_antigua: string;
    dias: number;
    detalle: { numero: string; fecha: string; dias: number; carga: string }[];
};

type ClientePago = {
    cliente: string;
    cobros: number;
    montos: Monto[];
    ultimo: string;
    detalle: {
        numero: string;
        fecha: string;
        simbolo: string;
        monto: number;
        operacion: string | null;
    }[];
};

type Reporte = {
    mes: string | null;
    meses: { value: string; label: string }[];
    desde: string;
    por_facturar: { guias: number; clientes: ClientePorFacturar[] };
    facturado: { facturas: number; montos: Monto[] };
    por_cobrar: {
        facturas: number;
        montos: Monto[];
        vencido: Monto[];
        facturas_vencidas: number;
        tramos: {
            tramo: string;
            facturas: number;
            monto: number;
            vencido: boolean;
        }[];
        clientes: ClienteDeuda[];
    };
    cobrado: { cobros: number; montos: Monto[]; clientes: ClientePago[] };
    /** De lo facturado en soles: lo que entró, lo que falta al día y lo vencido. */
    avance: {
        facturado: number;
        cobrado: number;
        al_dia: number;
        vencido: number;
    };
    detraccion: {
        por_depositar: number;
        facturas_por_depositar: number;
        sin_cobrar: number;
        facturas_sin_cobrar: number;
        detalle: {
            numero: string;
            cliente: string;
            monto: number;
            fecha_pago: string | null;
            dias: number;
        }[];
    };
    saldo_a_favor: Monto[];
    por_mes: {
        mes: string;
        label: string;
        facturado: number;
        cobrado: number;
    }[];
};

type Pestana = 'cobrar' | 'facturar' | 'pagaron' | 'detraccion';

/** Siempre con céntimos: «40,416.00» no se confunde con cuarenta soles. */
function dinero(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** La misma cifra dicha en palabras, para leerla sin contar dígitos. */
function enPalabras(monto: number): string | null {
    if (monto >= 1_000_000) {
        return `${(monto / 1_000_000).toLocaleString('es-PE', { maximumFractionDigits: 2 })} millones`;
    }

    if (monto >= 1_000) {
        return `${(monto / 1_000).toLocaleString('es-PE', { maximumFractionDigits: 1 })} mil`;
    }

    return null;
}

function plural(cantidad: number, uno: string, varios: string): string {
    return `${cantidad} ${cantidad === 1 ? uno : varios}`;
}

/** El monto con el símbolo chico y la cifra grande; los dólares, debajo. */
function Montos({
    montos,
    className,
}: {
    montos: Monto[];
    className?: string;
}) {
    const lista = montos.length > 0 ? montos : [{ simbolo: 'S/', monto: 0 }];

    return (
        <>
            {lista.map((monto, indice) => (
                <span
                    key={monto.simbolo}
                    className={cn(
                        'block whitespace-nowrap tabular-nums',
                        indice > 0 && 'text-xs font-normal opacity-80',
                        className,
                    )}
                >
                    <span className="mr-1 text-[0.7em] font-medium opacity-70">
                        {monto.simbolo}
                    </span>
                    {dinero(monto.monto)}
                </span>
            ))}
        </>
    );
}

const TONOS = {
    rojo: {
        texto: 'text-red-700 dark:text-red-400',
        chip: 'bg-red-500/10 text-red-700 dark:text-red-400',
        activa: 'border-red-500 ring-2 ring-red-500/25',
    },
    ambar: {
        texto: 'text-amber-700 dark:text-amber-500',
        chip: 'bg-amber-500/10 text-amber-700 dark:text-amber-500',
        activa: 'border-amber-500 ring-2 ring-amber-500/25',
    },
    verde: {
        texto: 'text-emerald-700 dark:text-emerald-400',
        chip: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        activa: 'border-emerald-500 ring-2 ring-emerald-500/25',
    },
    violeta: {
        texto: 'text-violet-700 dark:text-violet-400',
        chip: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        activa: 'border-violet-500 ring-2 ring-violet-500/25',
    },
} as const;

/**
 * Una de las cuatro respuestas. Es también la pestaña: al tocarla, abajo se
 * ve su detalle.
 */
function TarjetaPestana({
    activa,
    onElegir,
    tono,
    icono,
    titulo,
    valor,
    pie,
}: {
    activa: boolean;
    onElegir: () => void;
    tono: keyof typeof TONOS;
    icono: React.ReactNode;
    titulo: string;
    valor: React.ReactNode;
    pie: React.ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={activa}
            onClick={onElegir}
            className={cn(
                'flex min-w-0 flex-col gap-1.5 rounded-2xl border bg-card p-3 text-left transition-shadow sm:p-4',
                activa ? TONOS[tono].activa : 'active:bg-muted',
            )}
        >
            <span className="flex items-center gap-2 text-xs font-medium text-muted-foreground sm:text-sm">
                <span
                    className={cn(
                        'grid size-7 shrink-0 place-items-center rounded-lg',
                        TONOS[tono].chip,
                    )}
                >
                    {icono}
                </span>
                <span className="leading-tight">{titulo}</span>
            </span>
            <span
                className={cn(
                    'text-lg leading-tight font-semibold sm:text-2xl',
                    TONOS[tono].texto,
                )}
            >
                {valor}
            </span>
            <span className="text-xs leading-snug text-muted-foreground">
                {pie}
            </span>
        </button>
    );
}

/** El enlace a Contabilidad con un filtro puesto. */
function aContabilidad(filtros: Record<string, string | null>): string {
    return contabilidad.index.url({
        query: Object.fromEntries(
            Object.entries(filtros).filter(([, valor]) => Boolean(valor)),
        ) as Record<string, string>,
    });
}

/** Cuántas filas se ven al abrir un cliente; el resto, en Contabilidad. */
const FILAS_VISIBLES = 8;

/** Un cliente que se abre para ver sus facturas o sus GR. */
function TarjetaCliente({
    cliente,
    monto,
    nota,
    etiqueta,
    barra,
    verTodas,
    children,
}: {
    cliente: string;
    monto: React.ReactNode;
    nota: React.ReactNode;
    /** El aviso corto al costado del nombre: «vencido», «al día». */
    etiqueta?: { texto: string; tono: keyof typeof TONOS };
    /** De 0 a 1: cuánto pesa frente al primero de la lista. */
    barra?: { parte: number; color: string };
    verTodas?: { href: string; texto: string };
    children: React.ReactNode;
}) {
    const [abierto, setAbierto] = useState(false);

    return (
        <li className="overflow-hidden rounded-2xl border bg-card">
            <button
                type="button"
                onClick={() => setAbierto(!abierto)}
                aria-expanded={abierto}
                className="flex w-full flex-col gap-2 p-4 text-left active:bg-muted"
            >
                <span className="flex w-full items-start gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-full bg-muted text-sm font-semibold text-muted-foreground uppercase">
                        {cliente.slice(0, 1)}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span className="truncate font-medium">
                                {cliente}
                            </span>
                            {etiqueta && (
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap',
                                        TONOS[etiqueta.tono].chip,
                                    )}
                                >
                                    {etiqueta.texto}
                                </span>
                            )}
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            {nota}
                        </span>
                    </span>
                    <span className="shrink-0 text-right text-base font-semibold">
                        {monto}
                    </span>
                    <ChevronDown
                        className={cn(
                            'mt-1 size-4 shrink-0 text-muted-foreground transition-transform',
                            abierto && 'rotate-180',
                        )}
                    />
                </span>
                {barra && (
                    <span className="block h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <span
                            className={cn(
                                'block h-full rounded-full',
                                barra.color,
                            )}
                            style={{
                                width: `${Math.max(barra.parte * 100, 2)}%`,
                            }}
                        />
                    </span>
                )}
            </button>
            {abierto && (
                <div className="border-t bg-muted/30">
                    <ul className="divide-y">{children}</ul>
                    {verTodas && (
                        <Link
                            href={verTodas.href}
                            className="flex items-center justify-center gap-1 border-t px-4 py-3 text-sm font-medium text-primary active:bg-muted"
                        >
                            {verTodas.texto}
                            <ChevronRight className="size-4" />
                        </Link>
                    )}
                </div>
            )}
        </li>
    );
}

/** Una factura o una GR dentro de un cliente: toda la fila lleva a su detalle. */
function FilaEnlace({
    href,
    titulo,
    nota,
    alerta,
    derecha,
}: {
    href: string;
    titulo: string;
    nota: React.ReactNode;
    alerta?: string | null;
    derecha?: React.ReactNode;
}) {
    return (
        <li>
            <Link
                href={href}
                className="flex items-center gap-3 px-4 py-3 text-sm active:bg-muted"
            >
                <span className="min-w-0 flex-1">
                    <span className="block font-mono font-medium">
                        {titulo}
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        {nota}
                    </span>
                    {alerta && (
                        <span className="block text-xs font-medium text-red-700 dark:text-red-400">
                            {alerta}
                        </span>
                    )}
                </span>
                {derecha && (
                    <span className="shrink-0 text-right font-medium">
                        {derecha}
                    </span>
                )}
                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
            </Link>
        </li>
    );
}

function Vacio({ children }: { children: React.ReactNode }) {
    return (
        <p className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

/** De lo facturado, cuánto entró, cuánto falta al día y cuánto está vencido. */
function Avance({
    avance,
    delMes,
}: {
    avance: Reporte['avance'];
    delMes: boolean;
}) {
    const total = Math.max(avance.facturado, 0.01);
    const porcentaje = Math.round((avance.cobrado / total) * 100);
    const partes = [
        { clave: 'Cobrado', monto: avance.cobrado, color: 'bg-emerald-500' },
        {
            clave: 'Por cobrar al día',
            monto: avance.al_dia,
            color: 'bg-amber-400',
        },
        { clave: 'Vencido', monto: avance.vencido, color: 'bg-red-500' },
    ];

    return (
        <section className="rounded-2xl border bg-card p-4">
            <div className="flex items-end justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">
                        {delMes
                            ? 'De lo facturado por los viajes del mes ya entró'
                            : 'De lo facturado ya entró'}
                    </p>
                    <p className="text-3xl font-semibold tabular-nums">
                        {porcentaje}%
                    </p>
                </div>
                <p className="text-right text-xs text-muted-foreground">
                    Facturado a clientes
                    <span className="block text-sm font-semibold text-foreground">
                        <Montos
                            montos={[
                                { simbolo: 'S/', monto: avance.facturado },
                            ]}
                        />
                    </span>
                </p>
            </div>

            <div className="mt-3 flex h-3 w-full overflow-hidden rounded-full bg-muted">
                {partes.map((parte) => (
                    <span
                        key={parte.clave}
                        className={parte.color}
                        style={{ width: `${(parte.monto / total) * 100}%` }}
                    />
                ))}
            </div>

            <ul className="mt-3 flex flex-col gap-1.5 text-sm sm:flex-row sm:gap-6">
                {partes.map((parte) => (
                    <li
                        key={parte.clave}
                        className="flex items-center justify-between gap-3 sm:flex-col sm:items-start sm:gap-0"
                    >
                        <span className="flex items-center gap-2 text-muted-foreground">
                            <span
                                className={cn(
                                    'size-2.5 rounded-full',
                                    parte.color,
                                )}
                            />
                            {parte.clave}
                        </span>
                        <span className="font-medium">
                            <Montos
                                montos={[{ simbolo: 'S/', monto: parte.monto }]}
                            />
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function ReporteCobranza({ reporte }: { reporte: Reporte }) {
    const [pestana, setPestana] = useState<Pestana>('cobrar');
    const detalle = useRef<HTMLDivElement>(null);

    // En el celular el detalle queda debajo de las tarjetas: al elegir una,
    // la pantalla baja sola hasta él.
    const abrir = (elegida: Pestana) => {
        setPestana(elegida);
        requestAnimationFrame(() =>
            detalle.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            }),
        );
    };

    const elegirMes = (mes: string | null) =>
        router.get(reporteRutas.index().url, mes ? { mes } : {}, {
            preserveScroll: true,
            preserveState: true,
        });

    const periodo =
        reporte.meses.find((mes) => mes.value === reporte.mes)?.label ??
        `Desde el ${formatearFecha(reporte.desde)}`;
    const mayorDelMes = Math.max(
        1,
        ...reporte.por_mes.map((mes) => Math.max(mes.facturado, mes.cobrado)),
    );
    const { por_cobrar: porCobrar, por_facturar: porFacturar } = reporte;
    const { cobrado, detraccion } = reporte;
    const porCobrarSoles = porCobrar.montos[0]?.monto ?? 0;
    const cobradoSoles = cobrado.montos[0]?.monto ?? 0;

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 pb-24 md:p-6">
            <Head title="Resumen de cobranza" />

            <div>
                <h1 className="text-xl font-semibold">Resumen de cobranza</h1>
                <p className="text-sm text-muted-foreground">{periodo}</p>
            </div>

            {/* El período: se desliza con el dedo si no entra. */}
            <div className="sticky top-0 z-10 -mx-4 flex gap-2 overflow-x-auto bg-background/95 px-4 py-2 backdrop-blur md:mx-0 md:px-0">
                {[{ value: null, label: 'Todo' }, ...reporte.meses].map(
                    (mes) => (
                        <button
                            key={mes.value ?? 'todo'}
                            type="button"
                            onClick={() => elegirMes(mes.value)}
                            aria-pressed={reporte.mes === mes.value}
                            className={cn(
                                'shrink-0 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap',
                                reporte.mes === mes.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-card text-muted-foreground',
                            )}
                        >
                            {mes.label}
                        </button>
                    ),
                )}
            </div>

            <Avance avance={reporte.avance} delMes={reporte.mes !== null} />

            {/* Las cuatro respuestas: cada una abre su detalle abajo. */}
            <div role="tablist" className="grid grid-cols-2 gap-3">
                <TarjetaPestana
                    activa={pestana === 'cobrar'}
                    onElegir={() => abrir('cobrar')}
                    tono="ambar"
                    icono={<Hourglass className="size-4" />}
                    titulo="Facturado sin cobrar"
                    valor={<Montos montos={porCobrar.montos} />}
                    pie={
                        <>
                            {enPalabras(porCobrarSoles) &&
                                `${enPalabras(porCobrarSoles)} · `}
                            {plural(porCobrar.facturas, 'factura', 'facturas')}
                        </>
                    }
                />
                <TarjetaPestana
                    activa={pestana === 'facturar'}
                    onElegir={() => abrir('facturar')}
                    tono="rojo"
                    icono={<FileWarning className="size-4" />}
                    titulo="Falta facturar"
                    valor={`${porFacturar.guias} GR`}
                    pie={`viajes sin factura, de ${plural(porFacturar.clientes.length, 'cliente', 'clientes')}`}
                />
                <TarjetaPestana
                    activa={pestana === 'pagaron'}
                    onElegir={() => abrir('pagaron')}
                    tono="verde"
                    icono={<CircleCheck className="size-4" />}
                    titulo="Ya pagaron"
                    valor={<Montos montos={cobrado.montos} />}
                    pie={
                        <>
                            {enPalabras(cobradoSoles) &&
                                `${enPalabras(cobradoSoles)} · `}
                            {plural(cobrado.cobros, 'depósito', 'depósitos')}
                        </>
                    }
                />
                <TarjetaPestana
                    activa={pestana === 'detraccion'}
                    onElegir={() => abrir('detraccion')}
                    tono="violeta"
                    icono={<Landmark className="size-4" />}
                    titulo="Detracción que Paty debe depositar"
                    valor={
                        <Montos
                            montos={[
                                {
                                    simbolo: 'S/',
                                    monto: detraccion.por_depositar,
                                },
                            ]}
                        />
                    }
                    pie={plural(
                        detraccion.facturas_por_depositar,
                        'factura ya cobrada',
                        'facturas ya cobradas',
                    )}
                />
            </div>

            <div ref={detalle} className="scroll-mt-16" />

            {pestana === 'cobrar' && (
                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="text-base font-semibold">Quién debe</h2>
                        <p className="text-sm text-muted-foreground">
                            {reporte.mes !== null &&
                                'Por los viajes de este mes, se haya facturado cuando sea. '}
                            Del que más debe al que menos. Toca un cliente para
                            ver sus facturas.
                        </p>
                    </div>

                    {/* Cuánto de lo que deben ya pasó su fecha. */}
                    <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {porCobrar.tramos.map((tramo) => (
                            <li
                                key={tramo.tramo}
                                className={cn(
                                    'rounded-xl border p-3',
                                    tramo.vencido && tramo.monto > 0
                                        ? 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/30'
                                        : 'bg-card',
                                )}
                            >
                                <p className="text-xs text-muted-foreground">
                                    {tramo.vencido
                                        ? `Vencido ${tramo.tramo.toLowerCase()}`
                                        : tramo.tramo}
                                </p>
                                <p
                                    className={cn(
                                        'text-sm font-semibold',
                                        tramo.vencido &&
                                            tramo.monto > 0 &&
                                            TONOS.rojo.texto,
                                    )}
                                >
                                    <Montos
                                        montos={[
                                            {
                                                simbolo: 'S/',
                                                monto: tramo.monto,
                                            },
                                        ]}
                                    />
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {plural(
                                        tramo.facturas,
                                        'factura',
                                        'facturas',
                                    )}
                                </p>
                            </li>
                        ))}
                    </ul>

                    {porCobrar.clientes.length === 0 ? (
                        <Vacio>Nadie debe nada en este período.</Vacio>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {porCobrar.clientes.map((cliente) => (
                                <TarjetaCliente
                                    key={cliente.cliente}
                                    cliente={cliente.cliente}
                                    monto={<Montos montos={cliente.montos} />}
                                    etiqueta={
                                        cliente.dias_vencida > 0
                                            ? {
                                                  texto: `vencido hasta ${cliente.dias_vencida} d`,
                                                  tono: 'rojo',
                                              }
                                            : { texto: 'al día', tono: 'verde' }
                                    }
                                    nota={
                                        <>
                                            {plural(
                                                cliente.facturas,
                                                'factura',
                                                'facturas',
                                            )}
                                            {cliente.dias_vencida > 0 &&
                                                cliente.vencido[0] && (
                                                    <>
                                                        {' · vencido S/ '}
                                                        {dinero(
                                                            cliente.vencido[0]
                                                                .monto,
                                                        )}
                                                    </>
                                                )}
                                        </>
                                    }
                                    barra={{
                                        parte: cliente.parte,
                                        color:
                                            cliente.dias_vencida > 0
                                                ? 'bg-red-500'
                                                : 'bg-amber-400',
                                    }}
                                    verTodas={{
                                        href: aContabilidad({
                                            cliente: cliente.filtro,
                                            estado: 'facturado',
                                            mes: reporte.mes,
                                        }),
                                        texto:
                                            cliente.facturas > FILAS_VISIBLES
                                                ? `Ver las ${cliente.facturas} en Contabilidad`
                                                : 'Ver en Contabilidad',
                                    }}
                                >
                                    {cliente.detalle
                                        .slice(0, FILAS_VISIBLES)
                                        .map((factura) => (
                                            <FilaEnlace
                                                key={factura.numero}
                                                href={aContabilidad({
                                                    buscar: factura.numero,
                                                })}
                                                titulo={factura.numero}
                                                nota={
                                                    <>
                                                        Emitida el{' '}
                                                        {formatearFecha(
                                                            factura.fecha,
                                                        )}
                                                        {factura.parcial &&
                                                            ' · cobro parcial'}
                                                        {factura.repartida &&
                                                            ' · solo la parte de este mes'}
                                                        {(factura.dias_vencida ??
                                                            0) <= 0 &&
                                                            ` · vence el ${formatearFecha(factura.vence)}`}
                                                    </>
                                                }
                                                alerta={
                                                    (factura.dias_vencida ??
                                                        0) > 0
                                                        ? `Vencida hace ${factura.dias_vencida} días`
                                                        : null
                                                }
                                                derecha={
                                                    <Montos
                                                        montos={[
                                                            {
                                                                simbolo:
                                                                    factura.simbolo,
                                                                monto: factura.saldo,
                                                            },
                                                        ]}
                                                    />
                                                }
                                            />
                                        ))}
                                </TarjetaCliente>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            {pestana === 'facturar' && (
                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="text-base font-semibold">
                            Falta facturar
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Viajes hechos que todavía no tienen factura. Toca un
                            cliente para ver sus guías.
                        </p>
                    </div>

                    {porFacturar.clientes.length === 0 ? (
                        <Vacio>Todo está facturado en este período.</Vacio>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {porFacturar.clientes.map((cliente) => (
                                <TarjetaCliente
                                    key={cliente.cliente}
                                    cliente={cliente.cliente}
                                    monto={
                                        <span className="whitespace-nowrap tabular-nums">
                                            {cliente.guias}{' '}
                                            <span className="text-xs font-medium opacity-70">
                                                GR
                                            </span>
                                        </span>
                                    }
                                    etiqueta={
                                        cliente.dias > 30
                                            ? {
                                                  texto: `espera hace ${cliente.dias} d`,
                                                  tono: 'rojo',
                                              }
                                            : {
                                                  texto: `hace ${cliente.dias} d`,
                                                  tono: 'ambar',
                                              }
                                    }
                                    nota={`La más antigua es del ${formatearFecha(cliente.mas_antigua)}`}
                                    barra={{
                                        parte:
                                            cliente.guias /
                                            Math.max(
                                                porFacturar.clientes[0].guias,
                                                1,
                                            ),
                                        color:
                                            cliente.dias > 30
                                                ? 'bg-red-500'
                                                : 'bg-amber-400',
                                    }}
                                    verTodas={{
                                        href: aContabilidad({
                                            cliente: cliente.filtro,
                                            estado: 'sin_facturar',
                                            mes: reporte.mes,
                                        }),
                                        texto:
                                            cliente.guias > FILAS_VISIBLES
                                                ? `Ver las ${cliente.guias} en Contabilidad`
                                                : 'Ver en Contabilidad',
                                    }}
                                >
                                    {cliente.detalle
                                        .slice(0, FILAS_VISIBLES)
                                        .map((guia) => (
                                            <FilaEnlace
                                                key={guia.numero}
                                                href={aContabilidad({
                                                    buscar: guia.numero,
                                                })}
                                                titulo={guia.numero}
                                                nota={`${guia.carga} · ${formatearFecha(guia.fecha)}`}
                                                derecha={
                                                    <span
                                                        className={cn(
                                                            'text-xs whitespace-nowrap',
                                                            guia.dias > 30
                                                                ? TONOS.rojo
                                                                      .texto
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        hace {guia.dias} d
                                                    </span>
                                                }
                                            />
                                        ))}
                                </TarjetaCliente>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            {pestana === 'pagaron' && (
                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="text-base font-semibold">Ya pagaron</h2>
                        <p className="text-sm text-muted-foreground">
                            {reporte.mes !== null
                                ? 'Lo que ya entró por los viajes de este mes, por cliente, haya entrado cuando sea.'
                                : 'Lo que entró en este período, por cliente.'}{' '}
                            Toca uno para ver qué facturas pagó.
                        </p>
                    </div>

                    {cobrado.clientes.length === 0 ? (
                        <Vacio>No entró ningún pago en este período.</Vacio>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {cobrado.clientes.map((cliente) => (
                                <TarjetaCliente
                                    key={cliente.cliente}
                                    cliente={cliente.cliente}
                                    monto={
                                        <span className={TONOS.verde.texto}>
                                            <Montos montos={cliente.montos} />
                                        </span>
                                    }
                                    nota={`${plural(cliente.cobros, 'depósito', 'depósitos')} · el último, el ${formatearFecha(cliente.ultimo)}`}
                                    barra={{
                                        parte:
                                            (cliente.montos[0]?.monto ?? 0) /
                                            Math.max(
                                                cobrado.clientes[0].montos[0]
                                                    ?.monto ?? 1,
                                                1,
                                            ),
                                        color: 'bg-emerald-500',
                                    }}
                                >
                                    {cliente.detalle
                                        .slice(0, FILAS_VISIBLES)
                                        .map((pago) => (
                                            <FilaEnlace
                                                key={`${pago.numero}-${pago.fecha}`}
                                                href={aContabilidad({
                                                    buscar: pago.numero,
                                                })}
                                                titulo={pago.numero}
                                                nota={
                                                    <>
                                                        Pagada el{' '}
                                                        {formatearFecha(
                                                            pago.fecha,
                                                        )}
                                                        {pago.operacion &&
                                                            ` · op. ${pago.operacion}`}
                                                    </>
                                                }
                                                derecha={
                                                    <Montos
                                                        montos={[
                                                            {
                                                                simbolo:
                                                                    pago.simbolo,
                                                                monto: pago.monto,
                                                            },
                                                        ]}
                                                    />
                                                }
                                            />
                                        ))}
                                </TarjetaCliente>
                            ))}
                        </ul>
                    )}

                    {reporte.saldo_a_favor.length > 0 && (
                        <p className="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Hay adelantos de clientes sin aplicar por{' '}
                            {reporte.saldo_a_favor
                                .map(
                                    (saldo) =>
                                        `${saldo.simbolo} ${dinero(saldo.monto)}`,
                                )
                                .join(' y ')}
                            .
                        </p>
                    )}
                </section>
            )}

            {pestana === 'detraccion' && (
                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="text-base font-semibold">
                            Detracciones que Paty debe depositar
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            De clientes que ya pagaron el total de su factura.
                            Es plata que sale de ese depósito al Banco de la
                            Nación.
                        </p>
                    </div>

                    {detraccion.facturas_sin_cobrar > 0 && (
                        <p className="rounded-xl border bg-card p-3 text-sm text-muted-foreground">
                            Además vendrán{' '}
                            <span className="font-medium text-foreground">
                                S/ {dinero(detraccion.sin_cobrar)}
                            </span>{' '}
                            de{' '}
                            {plural(
                                detraccion.facturas_sin_cobrar,
                                'factura',
                                'facturas',
                            )}{' '}
                            que el cliente todavía no paga.
                        </p>
                    )}

                    {detraccion.detalle.length === 0 ? (
                        <Vacio>No hay detracciones por depositar.</Vacio>
                    ) : (
                        <ul className="divide-y overflow-hidden rounded-2xl border bg-card">
                            {detraccion.detalle.map((factura) => (
                                <FilaEnlace
                                    key={factura.numero}
                                    href={detracciones.index().url}
                                    titulo={factura.numero}
                                    nota={factura.cliente}
                                    alerta={
                                        factura.dias > 7
                                            ? `El cliente pagó hace ${factura.dias} días`
                                            : null
                                    }
                                    derecha={
                                        <Montos
                                            montos={[
                                                {
                                                    simbolo: 'S/',
                                                    monto: factura.monto,
                                                },
                                            ]}
                                        />
                                    }
                                />
                            ))}
                        </ul>
                    )}

                    <Link
                        href={detracciones.index().url}
                        className="flex items-center justify-center gap-1 rounded-2xl border bg-card px-4 py-3 text-sm font-medium text-primary active:bg-muted"
                    >
                        Registrar depósitos
                        <ChevronRight className="size-4" />
                    </Link>
                </section>
            )}

            <section className="flex flex-col gap-3">
                <div>
                    <h2 className="text-base font-semibold">Mes a mes</h2>
                    <p className="text-sm text-muted-foreground">
                        Las facturas que se emitieron y los depósitos que
                        entraron en cada mes, en soles. Por fecha de factura y
                        de depósito, no de viaje.
                    </p>
                </div>
                <ul className="flex flex-col gap-4 rounded-2xl border bg-card p-4">
                    {reporte.por_mes.map((mes) => (
                        <li key={mes.mes} className="flex flex-col gap-2">
                            <button
                                type="button"
                                onClick={() => elegirMes(mes.mes)}
                                className="flex items-center gap-1 self-start text-sm font-medium"
                            >
                                {mes.label}
                                <ChevronRight className="size-4 text-muted-foreground" />
                            </button>
                            {(
                                [
                                    ['Emitido', mes.facturado, 'bg-sky-500'],
                                    [
                                        'Depositado',
                                        mes.cobrado,
                                        'bg-emerald-500',
                                    ],
                                ] as const
                            ).map(([etiqueta, monto, color]) => (
                                <div
                                    key={etiqueta}
                                    className="flex flex-col gap-1"
                                >
                                    <div className="flex items-baseline justify-between gap-3 text-xs">
                                        <span className="text-muted-foreground">
                                            {etiqueta}
                                        </span>
                                        <span className="text-sm font-medium">
                                            <Montos
                                                montos={[
                                                    { simbolo: 'S/', monto },
                                                ]}
                                            />
                                        </span>
                                    </div>
                                    <span className="block h-2 w-full overflow-hidden rounded-full bg-muted">
                                        <span
                                            className={cn(
                                                'block h-full rounded-full',
                                                color,
                                            )}
                                            style={{
                                                width: `${(monto / mayorDelMes) * 100}%`,
                                            }}
                                        />
                                    </span>
                                </div>
                            ))}
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}

ReporteCobranza.layout = {
    breadcrumbs: [
        { title: 'Contabilidad', href: contabilidad.index().url },
        { title: 'Resumen', href: reporteRutas.index().url },
    ],
};
