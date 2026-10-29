import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { CircleCheck, Fuel, Search, Settings, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import {
    BarraCombustible,
    galones,
    soles,
} from '@/components/abastecimiento/barra-combustible';
import { RegistrarAbastecimientoDialog } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import type { InicialAbastecimiento } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import { ViaticoDialog } from '@/components/abastecimiento/viatico-dialog';
import type { AccionViatico } from '@/components/abastecimiento/viatico-dialog';
import { ViaticosTicket } from '@/components/abastecimiento/viaticos-ticket';
import { EstadoTallerBadge } from '@/components/taller/estado-taller';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import { colorTarjeta } from '@/lib/color-tarjeta';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    PuntoRegistro,
    RegistroAbastecimiento,
    TractoAbastecimiento,
    VueltaAbastecimiento,
} from '@/types/abastecimiento';

type Props = {
    vueltas: VueltaAbastecimiento[];
    recientes: RegistroAbastecimiento[];
    tractos: TractoAbastecimiento[];
    puntos: PuntoRegistro[];
    galonesTanqueLleno: number | null;
};

const ETAPA = {
    programada: { texto: 'Por salir', punto: 'bg-sky-500' },
    en_ida: { texto: 'Ida', punto: 'bg-amber-500' },
    en_vuelta: { texto: 'Vuelta', punto: 'bg-violet-500' },
    en_base: { texto: 'En base', punto: 'bg-emerald-500' },
} as const;

/**
 * Los viáticos del ticket en una barra: lo depositado contra lo que dice la
 * pizarra (lo depositado más lo pendiente), y debajo lo que va gastado en
 * total con el combustible.
 */
function BarraViaticos({ vuelta }: { vuelta: VueltaAbastecimiento }) {
    const total = vuelta.viatico_depositado + vuelta.viatico_pendiente;
    const gastado = vuelta.importe_registrado + vuelta.viatico_depositado;

    if (total === 0) {
        return (
            <span className="text-xs text-muted-foreground">
                Sin viáticos en la pizarra
            </span>
        );
    }

    const completo = vuelta.viatico_pendiente === 0;

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-center gap-2">
                <div className="h-2.5 w-28 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full bg-emerald-500"
                        style={{
                            width: `${Math.min(100, (vuelta.viatico_depositado / total) * 100)}%`,
                        }}
                    />
                </div>
                <span
                    className={cn(
                        'text-xs font-medium whitespace-nowrap tabular-nums',
                        completo
                            ? 'text-emerald-700 dark:text-emerald-400'
                            : 'text-amber-700 dark:text-amber-400',
                    )}
                >
                    {soles(vuelta.viatico_depositado)} / {soles(total)}
                </span>
            </div>
            {gastado > 0 && (
                <span className="text-[11px] text-muted-foreground tabular-nums">
                    Gasto del ticket: {soles(gastado)}
                </span>
            )}
        </div>
    );
}

const sinGuion = (texto: string) => texto.toLowerCase().replace(/-/g, '');

/** Lo que falta en total: null si algo depende de un tanque lleno sin galones. */
/**
 * Lo que falta en total: el tope de cada tramo menos lo autorizado. null si
 * algún tramo depende de un tanque lleno sin galones configurados.
 */
function faltaTotal(vuelta: VueltaAbastecimiento): number | null {
    if (vuelta.tramos.some((tramo) => tramo.falta === null)) {
        return null;
    }

    return vuelta.tramos.reduce((suma, tramo) => suma + (tramo.falta ?? 0), 0);
}

/**
 * El tablero de abastecimiento como tabla: una fila por unidad con su ticket
 * abierto, lo que le toca según la pizarra, lo autorizado y lo que falta.
 * Clic en la fila abre el detalle por punto, desde donde se registra.
 */
export default function AbastecimientoIndex({
    vueltas,
    recientes,
    tractos,
    puntos,
    galonesTanqueLleno,
}: Props) {
    const { puede } = usePermisos();
    const puedeRegistrar = puede('abastecimiento.registrar');
    const [buscar, setBuscar] = useState('');
    const [soloPendientes, setSoloPendientes] = useState(false);
    // Desde el expediente del ticket se llega con `?ticket=ID`: su ficha
    // se abre sola.
    const [abiertaId, setAbiertaId] = useState<number | null>(() => {
        const pedido = new URLSearchParams(
            typeof window === 'undefined' ? '' : window.location.search,
        ).get('ticket');

        return pedido ? Number(pedido) : null;
    });
    const [accionViatico, setAccionViatico] = useState<AccionViatico | null>(
        null,
    );
    const [registrando, setRegistrando] =
        useState<InicialAbastecimiento | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Abastecimiento', href: abastecimiento.index().url },
        ],
    });

    const filtradas = useMemo(() => {
        const texto = sinGuion(buscar.trim());

        return vueltas.filter((vuelta) => {
            if (soloPendientes && (faltaTotal(vuelta) ?? 1) <= 0) {
                return false;
            }

            return (
                texto === '' ||
                sinGuion(vuelta.tracto.placa).includes(texto) ||
                sinGuion(vuelta.codigo ?? '').includes(texto) ||
                (vuelta.conductor ?? '').toLowerCase().includes(texto)
            );
        });
    }, [buscar, soloPendientes, vueltas]);

    const pendientes = vueltas.filter(
        (vuelta) => (faltaTotal(vuelta) ?? 1) > 0,
    ).length;
    const abierta = vueltas.find((vuelta) => vuelta.id === abiertaId) ?? null;

    const quitar = (registro: RegistroAbastecimiento) => {
        if (
            !window.confirm(
                `¿Quitar ${galones(registro.galones)} de ${registro.placa}?`,
            )
        ) {
            return;
        }

        router.delete(abastecimiento.destroy(registro.id).url, {
            preserveScroll: true,
        });
    };

    /**
     * El registro con la unidad y el ticket puestos, y como galones lo que le
     * falta al tramo (el punto lo elige quien registra: el reparto varía).
     */
    const registrarEn = (vuelta: VueltaAbastecimiento, falta?: number | null) =>
        setRegistrando({
            vehiculo_id: vuelta.tracto.id,
            vuelta_id: vuelta.id,
            punto_abastecimiento_id: null,
            galones:
                falta ??
                vuelta.tramos.find((tramo) => (tramo.falta ?? 0) > 0)?.falta ??
                null,
        });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Abastecimiento" />

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex rounded-lg border p-0.5">
                        {(
                            [
                                [false, `Todas · ${vueltas.length}`],
                                [
                                    true,
                                    `Con combustible pendiente · ${pendientes}`,
                                ],
                            ] as const
                        ).map(([valor, etiqueta]) => (
                            <button
                                key={String(valor)}
                                type="button"
                                onClick={() => setSoloPendientes(valor)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm transition',
                                    soloPendientes === valor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {etiqueta}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="flex gap-2">
                    <div className="relative flex-1 lg:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={buscar}
                            onChange={(evento) =>
                                setBuscar(evento.target.value)
                            }
                            placeholder="Placa, ticket o conductor"
                            className="pl-8"
                        />
                    </div>
                    {puede('abastecimiento.configurar') && (
                        <Button variant="outline" asChild>
                            <Link href={configuracion.index().url}>
                                <Settings className="size-4" />
                                <span className="max-sm:hidden">Pizarra</span>
                            </Link>
                        </Button>
                    )}
                    {puedeRegistrar && (
                        <Button onClick={() => setRegistrando({})}>
                            <Fuel className="size-4" />
                            <span className="max-sm:hidden">Registrar</span>
                        </Button>
                    )}
                </div>
            </div>

            <div className="overflow-x-auto rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="w-28">Unidad</TableHead>
                            <TableHead className="w-24">Ticket</TableHead>
                            <TableHead className="w-24">Etapa</TableHead>
                            <TableHead>Tarjeta de la pizarra</TableHead>
                            <TableHead className="min-w-80">
                                Combustible
                            </TableHead>
                            <TableHead className="min-w-44">Viáticos</TableHead>
                            <TableHead className="w-32 text-right">
                                Falta
                            </TableHead>
                            <TableHead className="w-24" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filtradas.length === 0 && (
                            <TableRow className="hover:bg-transparent">
                                <TableCell
                                    colSpan={8}
                                    className="py-10 text-center text-sm text-muted-foreground"
                                >
                                    {vueltas.length === 0
                                        ? 'No hay tickets abiertos.'
                                        : 'Ningún ticket coincide.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {filtradas.map((vuelta) => {
                            const falta = faltaTotal(vuelta);
                            const etapa = ETAPA[vuelta.etapa];

                            return (
                                <TableRow
                                    key={vuelta.id}
                                    className="cursor-pointer"
                                    onClick={() => setAbiertaId(vuelta.id)}
                                >
                                    <TableCell className="font-mono font-semibold whitespace-nowrap">
                                        {formatearPlaca(vuelta.tracto.placa)}
                                    </TableCell>
                                    <TableCell
                                        className="font-mono text-xs text-muted-foreground"
                                        onClick={(evento) =>
                                            evento.stopPropagation()
                                        }
                                    >
                                        <Link
                                            href={
                                                vueltasRutas.show(vuelta.id).url
                                            }
                                            className="underline-offset-2 hover:text-foreground hover:underline"
                                            title="Ver el expediente del ticket"
                                        >
                                            {vuelta.codigo}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <span className="flex items-center gap-1.5 text-xs">
                                            <span
                                                className={cn(
                                                    'size-2 rounded-full',
                                                    etapa.punto,
                                                )}
                                            />
                                            {etapa.texto}
                                        </span>
                                        {vuelta.taller?.resultado !==
                                            'apto' && (
                                            <span className="mt-1 block">
                                                <EstadoTallerBadge
                                                    revision={vuelta.taller}
                                                    corto
                                                />
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-64">
                                        <div className="flex flex-wrap gap-1">
                                            {vuelta.tarjetas.length === 0 ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Sin tarjeta
                                                </span>
                                            ) : (
                                                vuelta.tarjetas.map(
                                                    (tarjeta) => (
                                                        <span
                                                            key={`${tarjeta.id}-${tarjeta.parada_id}`}
                                                            className={cn(
                                                                'truncate rounded px-1.5 py-0.5 text-[11px] font-medium',
                                                                colorTarjeta(
                                                                    tarjeta.color,
                                                                ).cabecera,
                                                            )}
                                                        >
                                                            {tarjeta.nombre}
                                                        </span>
                                                    ),
                                                )
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <BarraCombustible
                                            tramos={vuelta.tramos}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <BarraViaticos vuelta={vuelta} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {vuelta.combustible.length === 0 ? (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        ) : falta === 0 ? (
                                            <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                                <CircleCheck className="size-3.5" />
                                                Completo
                                            </span>
                                        ) : (
                                            <span className="text-xs font-medium text-amber-700 tabular-nums dark:text-amber-400">
                                                {falta === null
                                                    ? 'Tanque lleno'
                                                    : galones(falta)}
                                            </span>
                                        )}
                                        {vuelta.viatico_pendiente > 0 && (
                                            <span className="block text-xs text-amber-700 tabular-nums dark:text-amber-400">
                                                Viático{' '}
                                                {soles(
                                                    vuelta.viatico_pendiente,
                                                )}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        onClick={(evento) =>
                                            evento.stopPropagation()
                                        }
                                    >
                                        {puedeRegistrar && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={
                                                    vuelta.bloqueo_gastos !==
                                                    null
                                                }
                                                title={
                                                    vuelta.bloqueo_gastos ??
                                                    undefined
                                                }
                                                onClick={() =>
                                                    registrarEn(vuelta)
                                                }
                                            >
                                                <Fuel className="size-3.5" />
                                                Registrar
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Últimos registros</h2>
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Fecha</TableHead>
                                <TableHead>Unidad</TableHead>
                                <TableHead>Ticket</TableHead>
                                <TableHead>Punto</TableHead>
                                <TableHead className="text-right">
                                    Galones
                                </TableHead>
                                <TableHead className="text-right">
                                    S/ gal
                                </TableHead>
                                <TableHead className="text-right">
                                    Importe
                                </TableHead>
                                <TableHead>Registró</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recientes.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={9}
                                        className="py-6 text-center text-sm text-muted-foreground"
                                    >
                                        Todavía no se registró combustible.
                                    </TableCell>
                                </TableRow>
                            )}
                            {recientes.map((registro) => (
                                <TableRow key={registro.id}>
                                    <TableCell className="whitespace-nowrap tabular-nums">
                                        {formatearFecha(registro.fecha)}
                                    </TableCell>
                                    <TableCell className="font-mono">
                                        {formatearPlaca(registro.placa)}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {registro.vuelta ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {registro.punto ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {galones(registro.galones)}
                                        {registro.tanque_lleno && ' (full)'}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {registro.precio_galon?.toFixed(3) ??
                                            '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {soles(registro.importe)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {registro.registrado_por ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {puedeRegistrar && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Quitar registro"
                                                onClick={() => quitar(registro)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </section>

            <Sheet
                open={abierta !== null}
                onOpenChange={(abierto) => !abierto && setAbiertaId(null)}
            >
                <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-lg">
                    {abierta && (
                        <>
                            <SheetHeader className="border-b">
                                <SheetTitle className="flex items-center gap-2">
                                    <span className="font-mono">
                                        {formatearPlaca(abierta.tracto.placa)}
                                    </span>
                                    <span className="font-mono text-sm text-muted-foreground">
                                        {abierta.codigo}
                                    </span>
                                </SheetTitle>
                                <SheetDescription>
                                    {abierta.conductor ?? 'Sin conductor'}
                                    {abierta.inicio &&
                                        ` · sale ${formatearFecha(abierta.inicio)}`}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="flex flex-col gap-6 p-4">
                                <section className="flex flex-col gap-2">
                                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Recorrido
                                    </h3>
                                    <ol className="flex flex-col gap-1 text-sm">
                                        {abierta.recorrido.map(
                                            (tramo, indice) => (
                                                <li
                                                    key={indice}
                                                    className="flex items-center gap-2"
                                                >
                                                    <span
                                                        className={cn(
                                                            'w-12 text-xs font-medium',
                                                            tramo.sentido ===
                                                                'ida'
                                                                ? 'text-amber-700 dark:text-amber-400'
                                                                : 'text-violet-700 dark:text-violet-400',
                                                        )}
                                                    >
                                                        {tramo.sentido === 'ida'
                                                            ? 'Ida'
                                                            : 'Vuelta'}
                                                    </span>
                                                    <span>{tramo.texto}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {tramo.carga}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ol>
                                </section>

                                <section className="flex flex-col gap-3">
                                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Combustible
                                    </h3>
                                    {abierta.tramos.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Ninguna tarjeta de la pizarra calza
                                            con este ticket todavía.
                                        </p>
                                    ) : (
                                        <>
                                            <BarraCombustible
                                                tramos={abierta.tramos}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                El total de cada tramo es el
                                                tope; los puntos son una
                                                sugerencia. Lo que se cargue en
                                                cualquier punto cuenta contra el
                                                tope.
                                            </p>
                                            {abierta.bloqueo_gastos !==
                                                null && (
                                                <div className="flex flex-col items-start gap-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                                    {abierta.taller
                                                        ?.resultado !==
                                                        'apto' && (
                                                        <EstadoTallerBadge
                                                            revision={
                                                                abierta.taller
                                                            }
                                                        />
                                                    )}
                                                    {abierta.bloqueo_gastos}
                                                </div>
                                            )}
                                            {puedeRegistrar &&
                                                abierta.bloqueo_gastos ===
                                                    null &&
                                                abierta.tramos.map(
                                                    (tramo) =>
                                                        tramo.falta !== 0 && (
                                                            <Button
                                                                key={
                                                                    tramo.sentido
                                                                }
                                                                variant="outline"
                                                                size="sm"
                                                                className="self-start"
                                                                onClick={() =>
                                                                    registrarEn(
                                                                        abierta,
                                                                        tramo.falta,
                                                                    )
                                                                }
                                                            >
                                                                <Fuel className="size-3.5" />
                                                                Registrar
                                                                {tramo.falta
                                                                    ? ` · faltan ${galones(tramo.falta)} de la ${tramo.sentido === 'ida' ? 'ida' : 'vuelta'}`
                                                                    : ''}
                                                            </Button>
                                                        ),
                                                )}
                                        </>
                                    )}
                                    {abierta.combustible.some(
                                        (fila) => fila.registrado > 0,
                                    ) && (
                                        <div className="flex flex-col gap-1">
                                            <span className="flex justify-between text-xs font-medium text-muted-foreground">
                                                Autorizado por punto
                                                {abierta.importe_registrado >
                                                    0 && (
                                                    <span className="tabular-nums">
                                                        {soles(
                                                            abierta.importe_registrado,
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                            <ul className="flex flex-col gap-0.5 text-sm">
                                                {abierta.combustible
                                                    .filter(
                                                        (fila) =>
                                                            fila.registrado > 0,
                                                    )
                                                    .map((fila) => (
                                                        <li
                                                            key={
                                                                fila.punto_id ??
                                                                fila.punto
                                                            }
                                                            className="flex justify-between"
                                                        >
                                                            <span>
                                                                {fila.punto}
                                                            </span>
                                                            <span className="tabular-nums">
                                                                {galones(
                                                                    fila.registrado,
                                                                )}
                                                            </span>
                                                        </li>
                                                    ))}
                                            </ul>
                                        </div>
                                    )}
                                </section>

                                <ViaticosTicket
                                    vuelta={abierta}
                                    puedeGestionar={puede(
                                        'abastecimiento.viaticos',
                                    )}
                                    onAccion={setAccionViatico}
                                />
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

            {accionViatico && (
                <ViaticoDialog
                    key={JSON.stringify(accionViatico)}
                    accion={accionViatico}
                    onCerrar={() => setAccionViatico(null)}
                />
            )}

            {registrando && (
                <RegistrarAbastecimientoDialog
                    key={JSON.stringify(registrando)}
                    open
                    onOpenChange={(abierto) => !abierto && setRegistrando(null)}
                    inicial={registrando}
                    tractos={tractos}
                    puntos={puntos}
                    galonesTanqueLleno={galonesTanqueLleno}
                />
            )}
        </div>
    );
}
