import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Fuel, Search, Settings, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import { RegistrarAbastecimientoDialog } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import type { InicialAbastecimiento } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
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
    CombustiblePunto,
    RegistroAbastecimiento,
    TractoAbastecimiento,
    VueltaAbastecimiento,
} from '@/types/abastecimiento';

type Props = {
    vueltas: VueltaAbastecimiento[];
    recientes: RegistroAbastecimiento[];
    tractos: TractoAbastecimiento[];
    puntos: { id: number; nombre: string }[];
    galonesTanqueLleno: number | null;
};

const ETAPA = {
    programada: { texto: 'Por salir', punto: 'bg-sky-500' },
    en_ida: { texto: 'Ida', punto: 'bg-amber-500' },
    en_vuelta: { texto: 'Vuelta', punto: 'bg-violet-500' },
    en_base: { texto: 'En base', punto: 'bg-emerald-500' },
} as const;

const galones = (valor: number | null): string =>
    valor === null
        ? '—'
        : `${Number.isInteger(valor) ? valor : valor.toFixed(1)} gal`;

const sinGuion = (texto: string) => texto.toLowerCase().replace(/-/g, '');

/** Lo que falta en total: null si algo depende de un tanque lleno sin galones. */
function faltaTotal(vuelta: VueltaAbastecimiento): number | null {
    if (vuelta.combustible.some((fila) => fila.falta === null && fila.lleno)) {
        return null;
    }

    return vuelta.combustible.reduce(
        (suma, fila) => suma + (fila.falta ?? 0),
        0,
    );
}

function Barra({ fila }: { fila: CombustiblePunto }) {
    const avance =
        fila.previsto && fila.previsto > 0
            ? Math.min(100, (fila.registrado / fila.previsto) * 100)
            : fila.registrado > 0
              ? 100
              : 0;

    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
                className={cn(
                    'h-full rounded-full',
                    fila.falta === 0 ? 'bg-emerald-500' : 'bg-amber-500',
                )}
                style={{ width: `${avance}%` }}
            />
        </div>
    );
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
    const [abiertaId, setAbiertaId] = useState<number | null>(null);
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

    const registrarEn = (
        vuelta: VueltaAbastecimiento,
        fila?: CombustiblePunto,
    ) =>
        setRegistrando({
            vehiculo_id: vuelta.tracto.id,
            vuelta_id: vuelta.id,
            punto_abastecimiento_id: fila?.punto_id ?? null,
            galones: fila?.falta ?? null,
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
                            <TableHead className="w-24 text-right">
                                Previsto
                            </TableHead>
                            <TableHead className="w-28 text-right">
                                Autorizado
                            </TableHead>
                            <TableHead className="w-40">Falta</TableHead>
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
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {vuelta.codigo}
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
                                    <TableCell className="text-right tabular-nums">
                                        {vuelta.combustible.length === 0
                                            ? '—'
                                            : vuelta.total_previsto === null
                                              ? 'con full'
                                              : galones(vuelta.total_previsto)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {galones(vuelta.total_registrado)}
                                    </TableCell>
                                    <TableCell>
                                        {vuelta.combustible.length === 0 ? (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        ) : falta === 0 ? (
                                            <span className="text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                                Completo
                                            </span>
                                        ) : (
                                            <span className="text-xs font-medium text-amber-700 tabular-nums dark:text-amber-400">
                                                {falta === null
                                                    ? 'Tanque lleno'
                                                    : galones(falta)}
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
                                <TableHead>Registró</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recientes.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={7}
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
                                        Combustible por punto
                                    </h3>
                                    {abierta.combustible.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Ninguna tarjeta de la pizarra calza
                                            con este ticket todavía.
                                        </p>
                                    ) : (
                                        abierta.combustible.map((fila) => (
                                            <div
                                                key={
                                                    fila.punto_id ?? fila.punto
                                                }
                                                className="flex flex-col gap-1.5"
                                            >
                                                <div className="flex items-center justify-between gap-2 text-sm">
                                                    <span className="font-medium">
                                                        {fila.punto}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {galones(
                                                            fila.registrado,
                                                        )}{' '}
                                                        de{' '}
                                                        {fila.previsto === null
                                                            ? fila.lleno
                                                                ? 'tanque lleno'
                                                                : '—'
                                                            : galones(
                                                                  fila.previsto,
                                                              )}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Barra fila={fila} />
                                                    {puedeRegistrar &&
                                                        fila.falta !== 0 && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-7 shrink-0"
                                                                onClick={() =>
                                                                    registrarEn(
                                                                        abierta,
                                                                        fila,
                                                                    )
                                                                }
                                                            >
                                                                {fila.falta
                                                                    ? `Registrar ${galones(fila.falta)}`
                                                                    : 'Registrar'}
                                                            </Button>
                                                        )}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </section>

                                {abierta.viaticos.length > 0 && (
                                    <section className="flex flex-col gap-2">
                                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Viáticos según la pizarra
                                        </h3>
                                        <ul className="flex flex-col gap-1 text-sm">
                                            {abierta.viaticos.map(
                                                (viatico, indice) => (
                                                    <li
                                                        key={indice}
                                                        className="flex justify-between"
                                                    >
                                                        <span>
                                                            {viatico.concepto}
                                                        </span>
                                                        <span className="tabular-nums">
                                                            S/{' '}
                                                            {viatico.monto.toFixed(
                                                                2,
                                                            )}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </section>
                                )}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

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
