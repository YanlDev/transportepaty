import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CircleCheck, Fuel, Scale, Search, Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import conciliacion from '@/actions/App/Http/Controllers/ConciliacionCombustibleController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import {
    BarraCombustible,
    galones,
    soles,
} from '@/components/abastecimiento/barra-combustible';
import {
    ColaDeTrabajo,
    vouchersPendientes,
} from '@/components/abastecimiento/cola-de-trabajo';
import {
    CombustiblesDelTicket,
    DialogoCombustible,
    RutasDelTicket,
} from '@/components/abastecimiento/combustible-ticket';
import type { AccionCombustible } from '@/components/abastecimiento/combustible-ticket';
import { RegistrarAbastecimientoDialog } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import type { InicialAbastecimiento } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import { ViaticoDialog } from '@/components/abastecimiento/viatico-dialog';
import type { AccionViatico } from '@/components/abastecimiento/viatico-dialog';
import { ViaticosTicket } from '@/components/abastecimiento/viaticos-ticket';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Placa } from '@/components/placa';
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
import { EtapaBadge } from '@/components/vueltas/etapa-badge';
import { usePermisos } from '@/hooks/use-permisos';
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
    puedeExcederTope: boolean;
};

/**
 * Los viáticos del ticket en una barra: lo depositado contra lo que dice la
 * pizarra (lo depositado más lo pendiente), y debajo lo que va gastado en
 * total con el combustible.
 */
function BarraViaticos({ vuelta }: { vuelta: VueltaAbastecimiento }) {
    const total = vuelta.viatico_depositado + vuelta.viatico_pendiente;
    const gastado = vuelta.importe_estimado + vuelta.viatico_depositado;

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
                <div className="h-2.5 w-16 shrink-0 overflow-hidden rounded-full bg-muted">
                    <div
                        className={cn(
                            'h-full rounded-full',
                            completo ? 'bg-emerald-500' : 'bg-primary',
                        )}
                        style={{
                            width: `${Math.min(100, (vuelta.viatico_depositado / total) * 100)}%`,
                        }}
                    />
                </div>
                <span
                    className={cn(
                        'text-xs whitespace-nowrap tabular-nums',
                        completo
                            ? 'font-medium text-emerald-700 dark:text-emerald-400'
                            : 'text-muted-foreground',
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

/**
 * Lo que falta en total: el tope de cada tramo menos lo autorizado. null si
 * algún tramo no tiene su tarjeta confirmada (o tiene galones por definir).
 */
function faltaTotal(vuelta: VueltaAbastecimiento): number | null {
    if (vuelta.tramos.some((tramo) => tramo.falta === null)) {
        return null;
    }

    return vuelta.tramos.reduce((suma, tramo) => suma + (tramo.falta ?? 0), 0);
}

/**
 * En qué tramo va el ticket para abastecimiento: la ida es de quien sale o
 * va subiendo; la vuelta, de quien ya viene o llegó. Un ticket que arranca
 * en la costa no tiene ida (su ida no tiene ruta): desde que se programa lo
 * que hay que abastecerle es la vuelta.
 */
function tramoEnCurso(vuelta: VueltaAbastecimiento): 'ida' | 'regreso' {
    if (vuelta.etapa === 'en_vuelta' || vuelta.etapa === 'en_base') {
        return 'regreso';
    }

    const conRuta = (sentido: 'ida' | 'regreso') =>
        vuelta.tramos.some(
            (tramo) => tramo.sentido === sentido && tramo.rutas.length > 0,
        );

    return !conRuta('ida') && conRuta('regreso') ? 'regreso' : 'ida';
}

/**
 * El próximo paso del ticket en el tramo que se mira, en una palabra: lo que
 * está detenido primero (ámbar), después lo que toca hacer (azul).
 */
function loQueSigue(
    vuelta: VueltaAbastecimiento,
    sentido: 'ida' | 'regreso',
): { texto: string; tono: 'detenido' | 'accion' | 'listo' } {
    const tramo = vuelta.tramos.find((opcion) => opcion.sentido === sentido);

    if (vuelta.bloqueo_gastos !== null) {
        return { texto: 'OK de taller', tono: 'detenido' };
    }

    if (vuelta.despachos_pendientes > 0) {
        return { texto: 'Voucher', tono: 'detenido' };
    }

    if (tramo && tramo.rutas.length > 0 && !tramo.confirmada) {
        return { texto: 'Definir tope', tono: 'detenido' };
    }

    if (tramo && (tramo.falta ?? 0) > 0) {
        return { texto: 'Autorizar', tono: 'accion' };
    }

    if (vuelta.viatico_pendiente > 0) {
        return { texto: 'Viático', tono: 'accion' };
    }

    if (vuelta.llegada_el !== null && vuelta.km_llegada === null) {
        return { texto: 'Km de llegada', tono: 'accion' };
    }

    return { texto: 'Al día', tono: 'listo' };
}

const TRAMOS = [
    { sentido: 'ida', titulo: 'Ida' },
    { sentido: 'regreso', titulo: 'Vuelta' },
] as const;

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
    puedeExcederTope,
}: Props) {
    const { puede } = usePermisos();
    const puedeRegistrar = puede('abastecimiento.registrar');
    const [buscar, setBuscar] = useState('');
    const [soloPendientes, setSoloPendientes] = useState(false);
    // La pantalla se parte por el tramo que cada ticket tiene en curso: la
    // ida y la vuelta se abastecen en momentos y lugares distintos.
    const [sentido, setSentido] = useState<'ida' | 'regreso'>(() =>
        // Si nadie está en ida, se abre en la vuelta: no en una lista vacía.
        vueltas.length === 0 ||
        vueltas.some((vuelta) => tramoEnCurso(vuelta) === 'ida')
            ? 'ida'
            : 'regreso',
    );
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
    const [accionCombustible, setAccionCombustible] =
        useState<AccionCombustible | null>(null);

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
    const delTramo = filtradas.filter(
        (vuelta) => tramoEnCurso(vuelta) === sentido,
    );
    const abierta = vueltas.find((vuelta) => vuelta.id === abiertaId) ?? null;

    /**
     * El registro con la unidad, el ticket y el tramo puestos, y como galones
     * lo que le falta al tramo (el punto lo elige quien registra: el reparto
     * varía).
     */
    const registrarEn = (
        vuelta: VueltaAbastecimiento,
        sentido?: 'ida' | 'regreso',
    ) => {
        const tramo =
            vuelta.tramos.find((opcion) => opcion.sentido === sentido) ??
            vuelta.tramos.find(
                (opcion) => opcion.confirmada && (opcion.falta ?? 0) > 0,
            );

        setRegistrando({
            vehiculo_id: vuelta.tracto.id,
            vuelta_id: vuelta.id,
            sentido: tramo?.confirmada ? tramo.sentido : null,
            galones: tramo?.falta ?? null,
        });
    };

    const vacio =
        vueltas.length === 0
            ? 'No hay tickets abiertos.'
            : filtradas.length === 0
              ? 'Ningún ticket coincide.'
              : sentido === 'ida'
                ? 'Ningún ticket por salir ni en ida.'
                : 'Ningún ticket en vuelta ni en base.';

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Abastecimiento" />

            <EncabezadoPantalla
                titulo="Abastecimiento"
                descripcion="El combustible y los viáticos de cada ticket, contra las rutas de cada tramo."
                icono={Fuel}
            />

            <ColaDeTrabajo
                vueltas={vueltas}
                puedeRegistrar={puedeRegistrar}
                onVoucher={(pendiente) =>
                    setAccionCombustible({
                        tipo: 'despacho',
                        registro: pendiente.registro,
                    })
                }
                onAbrir={(vuelta) => setAbiertaId(vuelta.id)}
            />

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex rounded-lg border p-0.5">
                        {TRAMOS.map((tramo) => (
                            <button
                                key={tramo.sentido}
                                type="button"
                                onClick={() => setSentido(tramo.sentido)}
                                aria-pressed={sentido === tramo.sentido}
                                className={cn(
                                    'min-h-9 rounded-md px-4 py-1.5 text-sm font-medium transition pointer-coarse:min-h-11',
                                    sentido === tramo.sentido
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tramo.titulo} ·{' '}
                                {
                                    filtradas.filter(
                                        (vuelta) =>
                                            tramoEnCurso(vuelta) ===
                                            tramo.sentido,
                                    ).length
                                }
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => setSoloPendientes(!soloPendientes)}
                        aria-pressed={soloPendientes}
                        className={cn(
                            'min-h-9 rounded-full border px-3 text-sm transition pointer-coarse:min-h-11',
                            soloPendientes
                                ? 'border-primary bg-accent font-medium text-accent-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Solo con combustible pendiente · {pendientes}
                    </button>
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
                    <Button variant="outline" asChild>
                        <Link href={conciliacion.index().url}>
                            <Scale className="size-4" />
                            <span className="max-sm:hidden">Conciliación</span>
                        </Link>
                    </Button>
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
                            <span className="max-sm:hidden">Autorizar</span>
                        </Button>
                    )}
                </div>
            </div>

            {/* En el celular: una tarjeta por ticket, con su barra y los
                botones grandes. Tocarla abre la ficha completa. */}
            <div className="flex flex-col gap-2 md:hidden">
                {delTramo.length === 0 && (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {vacio}
                    </p>
                )}
                {delTramo.map((vuelta) => (
                    <article
                        key={vuelta.id}
                        className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                    >
                        <button
                            type="button"
                            onClick={() => setAbiertaId(vuelta.id)}
                            className="flex items-start justify-between gap-2 text-left"
                        >
                            <span className="flex flex-col gap-1">
                                <Placa
                                    placa={vuelta.tracto.placa}
                                    tamano="lg"
                                />
                                <span className="text-xs text-muted-foreground">
                                    {vuelta.conductor ?? 'Sin conductor'}
                                </span>
                            </span>
                            <span className="flex flex-col items-end gap-1">
                                <span className="font-mono text-xs text-muted-foreground">
                                    {vuelta.codigo}
                                </span>
                                <EtapaBadge etapa={vuelta.etapa} />
                            </span>
                        </button>
                        <BarraCombustible
                            tramos={vuelta.tramos}
                            sentido={sentido}
                        />
                        <BarraViaticos vuelta={vuelta} />
                        {vuelta.bloqueo_gastos !== null ? (
                            <p className="rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                {vuelta.bloqueo_gastos}
                            </p>
                        ) : (
                            puedeRegistrar && (
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        className="h-12 text-base"
                                        onClick={() =>
                                            registrarEn(vuelta, sentido)
                                        }
                                    >
                                        <Fuel className="size-5" />
                                        Combustible
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="h-12 text-base"
                                        onClick={() => setAbiertaId(vuelta.id)}
                                    >
                                        Viáticos y detalle
                                    </Button>
                                </div>
                            )
                        )}
                    </article>
                ))}
            </div>

            <div className="overflow-x-auto rounded-xl border bg-card max-md:hidden">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Unidad</TableHead>
                            <TableHead className="w-24">Ticket</TableHead>
                            <TableHead className="w-24">Etapa</TableHead>
                            <TableHead>Ruta</TableHead>
                            <TableHead className="min-w-60">
                                Combustible de la{' '}
                                {sentido === 'ida' ? 'ida' : 'vuelta'}
                            </TableHead>
                            <TableHead className="min-w-36">Viáticos</TableHead>
                            <TableHead className="w-32 text-right">
                                Falta
                            </TableHead>
                            <TableHead className="w-32">Sigue</TableHead>
                            {/* La acción queda fija a la derecha: en pantallas
                                angostas la tabla se desplaza por debajo. */}
                            <TableHead className="sticky right-0 w-24 bg-card" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {delTramo.length === 0 && (
                            <TableRow className="hover:bg-transparent">
                                <TableCell
                                    colSpan={9}
                                    className="py-10 text-center text-sm text-muted-foreground"
                                >
                                    {vacio}
                                </TableCell>
                            </TableRow>
                        )}
                        {delTramo.map((vuelta) => {
                            const tramo =
                                vuelta.tramos.find(
                                    (opcion) => opcion.sentido === sentido,
                                ) ?? null;
                            // Lo que quedó debiendo el otro tramo (la ida
                            // de un ticket que ya viene de vuelta).
                            const otro = vuelta.tramos.find(
                                (opcion) =>
                                    opcion.sentido !== sentido &&
                                    opcion.confirmada &&
                                    (opcion.falta ?? 0) > 0,
                            );
                            const sigue = loQueSigue(vuelta, sentido);

                            return (
                                <TableRow
                                    key={vuelta.id}
                                    className="cursor-pointer"
                                    onClick={() => setAbiertaId(vuelta.id)}
                                >
                                    <TableCell className="whitespace-nowrap">
                                        <span className="flex flex-col items-start gap-1">
                                            <Placa
                                                placa={vuelta.tracto.placa}
                                            />
                                            <span
                                                className="max-w-36 truncate text-xs text-muted-foreground"
                                                title={
                                                    vuelta.conductor ??
                                                    undefined
                                                }
                                            >
                                                {vuelta.conductor ??
                                                    'Sin conductor'}
                                            </span>
                                        </span>
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
                                        <EtapaBadge etapa={vuelta.etapa} />
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
                                            {vuelta.tarjetas
                                                .filter(
                                                    (tarjeta) =>
                                                        tarjeta.sentido ===
                                                        sentido,
                                                )
                                                .map((tarjeta) => (
                                                    <span
                                                        key={`${tarjeta.id}-${tarjeta.nombre}`}
                                                        className="truncate rounded bg-muted px-1.5 py-0.5 text-xs font-medium"
                                                    >
                                                        {tarjeta.nombre}
                                                    </span>
                                                ))}
                                            {!vuelta.tarjetas.some(
                                                (tarjeta) =>
                                                    tarjeta.sentido === sentido,
                                            ) && (
                                                <span className="text-sm text-muted-foreground">
                                                    Sin ruta
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <BarraCombustible
                                            tramos={vuelta.tramos}
                                            sentido={sentido}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <BarraViaticos vuelta={vuelta} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {tramo === null ? (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        ) : !tramo.confirmada ||
                                          tramo.falta === null ? (
                                            <span
                                                className="text-xs font-medium text-amber-700 dark:text-amber-400"
                                                title={
                                                    tramo.pendiente ?? undefined
                                                }
                                            >
                                                Sin tope
                                            </span>
                                        ) : tramo.falta === 0 ? (
                                            <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                                <CircleCheck className="size-3.5" />
                                                Completo
                                            </span>
                                        ) : (
                                            <span className="text-sm font-semibold tabular-nums">
                                                {galones(tramo.falta)}
                                            </span>
                                        )}
                                        {otro && (
                                            <span className="block text-xs text-muted-foreground tabular-nums">
                                                {otro.sentido === 'ida'
                                                    ? 'Ida'
                                                    : 'Vuelta'}
                                                : faltan {galones(otro.falta)}
                                            </span>
                                        )}
                                        {vuelta.despachos_pendientes > 0 && (
                                            <span className="block text-xs text-amber-700 tabular-nums dark:text-amber-400">
                                                {vuelta.despachos_pendientes}{' '}
                                                sin voucher
                                            </span>
                                        )}
                                        {vuelta.viatico_pendiente > 0 && (
                                            <span className="block text-xs text-muted-foreground tabular-nums">
                                                Viático{' '}
                                                {soles(
                                                    vuelta.viatico_pendiente,
                                                )}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-sm font-medium whitespace-nowrap',
                                            sigue.tono === 'detenido' &&
                                                'text-amber-700 dark:text-amber-400',
                                            sigue.tono === 'accion' &&
                                                'text-primary',
                                            sigue.tono === 'listo' &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {sigue.texto}
                                    </TableCell>
                                    <TableCell
                                        className="sticky right-0 bg-card"
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
                                                    registrarEn(vuelta, sentido)
                                                }
                                            >
                                                <Fuel className="size-3.5" />
                                                Autorizar
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
                                <TableHead>Tramo</TableHead>
                                <TableHead>Punto</TableHead>
                                <TableHead className="text-right">
                                    Autorizado
                                </TableHead>
                                <TableHead className="text-right">
                                    Despachado
                                </TableHead>
                                <TableHead className="text-right">
                                    S/ gal
                                </TableHead>
                                <TableHead className="text-right">
                                    Importe
                                </TableHead>
                                <TableHead>Registró</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recientes.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={10}
                                        className="py-6 text-center text-sm text-muted-foreground"
                                    >
                                        Todavía no se registró combustible.
                                    </TableCell>
                                </TableRow>
                            )}
                            {recientes.map((registro) => (
                                <TableRow
                                    key={registro.id}
                                    className={cn(
                                        registro.anulado &&
                                            'text-muted-foreground line-through',
                                    )}
                                    title={
                                        registro.anulado ? 'Anulado' : undefined
                                    }
                                >
                                    <TableCell className="whitespace-nowrap tabular-nums">
                                        {formatearFecha(registro.fecha)}
                                    </TableCell>
                                    <TableCell>
                                        <span className="flex flex-col">
                                            <span className="font-mono">
                                                {formatearPlaca(registro.placa)}
                                            </span>
                                            {registro.conductor && (
                                                <span
                                                    className="max-w-40 truncate text-xs text-muted-foreground"
                                                    title={registro.conductor}
                                                >
                                                    {registro.conductor}
                                                </span>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {registro.vuelta ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-xs">
                                        {registro.sentido === 'ida'
                                            ? 'Ida'
                                            : registro.sentido === 'regreso'
                                              ? 'Vuelta'
                                              : '—'}
                                    </TableCell>
                                    <TableCell>
                                        {registro.punto ?? '—'}
                                        {registro.fuera_de_lista && (
                                            <span className="ml-1.5 rounded border border-dashed px-1 text-[10px] text-muted-foreground">
                                                fuera de lista
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {galones(registro.galones)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {registro.galones_despachados === null
                                            ? 'Sin voucher'
                                            : galones(
                                                  registro.galones_despachados,
                                              )}
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
                                        Rutas de cada tramo
                                    </h3>
                                    <RutasDelTicket
                                        codigo={abierta.codigo}
                                        tramos={abierta.tramos}
                                        puedeElegirVariante={puedeRegistrar}
                                        onAccion={setAccionCombustible}
                                    />
                                </section>

                                <section className="flex flex-col gap-3">
                                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Combustible
                                    </h3>
                                    <BarraCombustible tramos={abierta.tramos} />
                                    <p className="text-xs text-muted-foreground">
                                        El tope de cada tramo es la suma de sus
                                        rutas; los puntos son una sugerencia.
                                    </p>
                                    {abierta.bloqueo_gastos !== null && (
                                        <div className="flex flex-col items-start gap-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                            {abierta.taller?.resultado !==
                                                'apto' && (
                                                <EstadoTallerBadge
                                                    revision={abierta.taller}
                                                />
                                            )}
                                            {abierta.bloqueo_gastos}
                                        </div>
                                    )}
                                    {puedeRegistrar &&
                                        abierta.bloqueo_gastos === null &&
                                        abierta.tramos
                                            .filter(
                                                (tramo) =>
                                                    tramo.confirmada &&
                                                    tramo.falta !== 0,
                                            )
                                            .map((tramo) => (
                                                <Button
                                                    key={tramo.sentido}
                                                    variant="outline"
                                                    size="sm"
                                                    className="self-start"
                                                    onClick={() =>
                                                        registrarEn(
                                                            abierta,
                                                            tramo.sentido,
                                                        )
                                                    }
                                                >
                                                    <Fuel className="size-3.5" />
                                                    Autorizar
                                                    {tramo.falta
                                                        ? ` · faltan ${galones(tramo.falta)} de la ${tramo.sentido === 'ida' ? 'ida' : 'vuelta'}`
                                                        : ''}
                                                </Button>
                                            ))}
                                    <span className="flex justify-between text-xs font-medium text-muted-foreground">
                                        Autorizado y despachado
                                        {abierta.importe_estimado > 0 && (
                                            <span className="tabular-nums">
                                                {soles(
                                                    abierta.importe_estimado,
                                                )}
                                            </span>
                                        )}
                                    </span>
                                    <CombustiblesDelTicket
                                        registros={abierta.abastecimientos}
                                        puedeGestionar={puedeRegistrar}
                                        puedeCorregir={puede(
                                            'abastecimiento.corregir',
                                        )}
                                        onAccion={setAccionCombustible}
                                    />
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
                    vueltas={vueltas}
                    puedeExcederTope={puedeExcederTope}
                />
            )}

            {accionCombustible && (
                <DialogoCombustible
                    key={JSON.stringify(accionCombustible)}
                    accion={accionCombustible}
                    puntos={puntos}
                    vueltas={vueltas}
                    vouchersPendientes={vouchersPendientes(vueltas).length}
                    onCerrar={() => setAccionCombustible(null)}
                />
            )}
        </div>
    );
}
