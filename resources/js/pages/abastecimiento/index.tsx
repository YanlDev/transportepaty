import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Fuel,
    Search,
    Settings,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import { RegistrarAbastecimientoDialog } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import type { InicialAbastecimiento } from '@/components/abastecimiento/registrar-abastecimiento-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
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

const galones = (valor: number): string =>
    `${Number.isInteger(valor) ? valor : valor.toFixed(1)} gal`;

/**
 * Un punto de la dotación: lo previsto por la pizarra, lo autorizado y lo
 * que falta, con una barra para verlo de un vistazo.
 */
function FilaCombustible({
    fila,
    onRegistrar,
}: {
    fila: CombustiblePunto;
    onRegistrar?: () => void;
}) {
    const avance =
        fila.previsto && fila.previsto > 0
            ? Math.min(100, (fila.registrado / fila.previsto) * 100)
            : fila.registrado > 0
              ? 100
              : 0;
    const completo = fila.falta === 0 && fila.previsto !== null;

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-center justify-between gap-2 text-sm">
                <span className="font-medium">{fila.punto}</span>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {galones(fila.registrado)} de{' '}
                    {fila.previsto === null
                        ? fila.lleno
                            ? 'tanque lleno'
                            : '—'
                        : galones(fila.previsto)}
                </span>
            </div>
            <div className="flex items-center gap-2">
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                    <div
                        className={cn(
                            'h-full rounded-full',
                            completo ? 'bg-emerald-500' : 'bg-amber-500',
                        )}
                        style={{ width: `${avance}%` }}
                    />
                </div>
                {fila.falta !== null && fila.falta > 0 && (
                    <span className="text-xs font-medium text-amber-700 tabular-nums dark:text-amber-400">
                        faltan {galones(fila.falta)}
                    </span>
                )}
                {completo && (
                    <span className="text-xs font-medium text-emerald-700 dark:text-emerald-400">
                        listo
                    </span>
                )}
                {onRegistrar && !completo && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 px-2 text-xs"
                        onClick={onRegistrar}
                    >
                        Registrar
                    </Button>
                )}
            </div>
        </div>
    );
}

/**
 * El tablero de abastecimiento: cada unidad con su vuelta abierta, lo que le
 * toca según la pizarra, lo que ya se le autorizó y lo que le falta.
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
    const [registrando, setRegistrando] =
        useState<InicialAbastecimiento | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Abastecimiento', href: abastecimiento.index().url },
        ],
    });

    const filtradas = useMemo(() => {
        const texto = buscar.trim().toLowerCase().replace(/-/g, '');

        if (texto === '') {
            return vueltas;
        }

        return vueltas.filter(
            (vuelta) =>
                vuelta.tracto.placa
                    .toLowerCase()
                    .replace(/-/g, '')
                    .includes(texto) ||
                (vuelta.conductor ?? '').toLowerCase().includes(texto) ||
                (vuelta.codigo ?? '').toLowerCase().includes(texto),
        );
    }, [buscar, vueltas]);

    const pendientes = vueltas.filter((vuelta) =>
        vuelta.combustible.some((fila) => (fila.falta ?? 0) > 0),
    ).length;

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

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Abastecimiento" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {vueltas.length}{' '}
                    {vueltas.length === 1
                        ? 'vuelta abierta'
                        : 'vueltas abiertas'}{' '}
                    · {pendientes} con combustible pendiente
                </p>
                <div className="flex gap-2">
                    <div className="relative flex-1 sm:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={buscar}
                            onChange={(evento) =>
                                setBuscar(evento.target.value)
                            }
                            placeholder="Placa, conductor o ticket"
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

            {filtradas.length === 0 ? (
                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {vueltas.length === 0
                        ? 'No hay vueltas abiertas.'
                        : 'Ninguna vuelta coincide con la búsqueda.'}
                </p>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    {filtradas.map((vuelta) => (
                        <section
                            key={vuelta.id}
                            className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                        >
                            <header className="flex items-start justify-between gap-2">
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="flex items-center gap-2">
                                        <span className="font-mono font-semibold">
                                            {formatearPlaca(
                                                vuelta.tracto.placa,
                                            )}
                                        </span>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {vuelta.codigo}
                                        </span>
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {vuelta.conductor ?? 'Sin conductor'}
                                        {vuelta.inicio &&
                                            ` · ${formatearFecha(vuelta.inicio)}`}
                                    </span>
                                </div>
                                <StatusBadge
                                    label={vuelta.estado_label}
                                    tone={
                                        vuelta.estado === 'en_curso'
                                            ? 'warning'
                                            : 'info'
                                    }
                                />
                            </header>

                            <ul className="flex flex-col gap-0.5 text-xs text-muted-foreground">
                                {vuelta.recorrido.map((tramo, indice) => (
                                    <li
                                        key={indice}
                                        className="flex items-center gap-1"
                                    >
                                        {tramo.sentido === 'ida' ? (
                                            <ArrowDownRight className="size-3 shrink-0" />
                                        ) : (
                                            <ArrowUpRight className="size-3 shrink-0" />
                                        )}
                                        <span className="truncate">
                                            {tramo.texto} · {tramo.carga}
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            {vuelta.tarjetas.length > 0 && (
                                <div className="flex flex-wrap gap-1">
                                    {vuelta.tarjetas.map((tarjeta) => (
                                        <span
                                            key={`${tarjeta.id}-${tarjeta.parada_id}`}
                                            className={cn(
                                                'rounded px-1.5 py-0.5 text-[11px] font-medium',
                                                colorTarjeta(tarjeta.color)
                                                    .cabecera,
                                            )}
                                        >
                                            {tarjeta.nombre}
                                        </span>
                                    ))}
                                </div>
                            )}

                            {vuelta.combustible.length === 0 ? (
                                <p className="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground">
                                    Ninguna tarjeta de la pizarra calza con esta
                                    vuelta todavía.
                                </p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {vuelta.combustible.map((fila) => (
                                        <FilaCombustible
                                            key={fila.punto_id ?? fila.punto}
                                            fila={fila}
                                            onRegistrar={
                                                puedeRegistrar
                                                    ? () =>
                                                          setRegistrando({
                                                              vehiculo_id:
                                                                  vuelta.tracto
                                                                      .id,
                                                              vuelta_id:
                                                                  vuelta.id,
                                                              punto_abastecimiento_id:
                                                                  fila.punto_id,
                                                              galones:
                                                                  fila.falta,
                                                          })
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {vuelta.viaticos.length > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Viáticos:{' '}
                                    {vuelta.viaticos
                                        .map(
                                            (viatico) =>
                                                `${viatico.concepto} S/ ${viatico.monto.toFixed(2)}`,
                                        )
                                        .join(' · ')}
                                </p>
                            )}
                        </section>
                    ))}
                </div>
            )}

            <section className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Últimos registros</h2>
                {recientes.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Todavía no se registró combustible.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Unidad</TableHead>
                                    <TableHead>Vuelta</TableHead>
                                    <TableHead>Punto</TableHead>
                                    <TableHead className="text-right">
                                        Galones
                                    </TableHead>
                                    <TableHead>Registró</TableHead>
                                    <TableHead className="w-0" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
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
                                                    onClick={() =>
                                                        quitar(registro)
                                                    }
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
                )}
            </section>

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
