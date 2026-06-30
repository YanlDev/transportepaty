import { Head, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    MapPin,
    Pause,
    Play,
    Repeat,
    Satellite,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { index as mapa } from '@/actions/App/Http/Controllers/MapaController';
import { index as recorridoUrl } from '@/actions/App/Http/Controllers/RecorridoController';
import { EmptyState } from '@/components/empty-state';
import { MapaLienzo } from '@/components/mapa/mapa-lienzo';
import { PanelRecorrido } from '@/components/mapa/panel-recorrido';
import { PanelVivo } from '@/components/mapa/panel-vivo';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { MarcadorVehiculo, Recorrido } from '@/types/fleet';

type Props = {
    marcadores: MarcadorVehiculo[];
    totalConGps: number;
    error: string | null;
    focusId: number | null;
    recorridoId: number | null;
    vehiculosGps: { id: number; placa: string }[];
};

const HOY = new Date().toISOString().slice(0, 10);
const VELOCIDADES = [1, 2, 4, 8, 16, 32, 64];
const PUNTOS_POR_SEG = 3; // a 1x

export default function MapaIndex({
    marcadores,
    totalConGps,
    error,
    focusId,
    recorridoId,
    vehiculosGps,
}: Props) {
    usePoll(30000, { only: ['marcadores'] });

    const [modo, setModo] = useState<'vivo' | 'recorrido'>(
        recorridoId ? 'recorrido' : 'vivo',
    );
    const [vehRec, setVehRec] = useState<number | null>(recorridoId);

    // Estado de la vista en vivo.
    const [seleccionados, setSeleccionados] = useState<Set<number>>(
        () => new Set(focusId ? [focusId] : marcadores.map((m) => m.id)),
    );

    // Estado de la vista de recorrido.
    const [preset, setPreset] = useState('ayer');
    const [fechaDia, setFechaDia] = useState(HOY);
    const [recorrido, setRecorrido] = useState<Recorrido | null>(null);
    const [cargando, setCargando] = useState(recorridoId !== null);
    const [errorRec, setErrorRec] = useState<string | null>(null);
    const [pos, setPos] = useState(0);
    const [playing, setPlaying] = useState(false);
    const [speed, setSpeed] = useState(4);
    const [repeat, setRepeat] = useState(true);

    const visibles = useMemo(
        () => marcadores.filter((m) => seleccionados.has(m.id)),
        [marcadores, seleccionados],
    );
    const enMovimiento = visibles.filter(
        (m) => m.estado === 'en_movimiento',
    ).length;

    const puntos = recorrido?.puntos ?? [];
    const hayRuta = recorrido !== null && puntos.length > 0;
    const placaRec = vehiculosGps.find((v) => v.id === vehRec)?.placa ?? '';

    // Carga del recorrido cuando estamos en ese modo (setState solo en callbacks).
    useEffect(() => {
        if (modo !== 'recorrido' || !vehRec) {
            return;
        }

        let activo = true;

        const query =
            preset === 'personalizado'
                ? { preset: 'personalizado', desde: fechaDia }
                : { preset };

        fetch(recorridoUrl(vehRec, { query }).url, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json().then((d) => ({ ok: r.ok, d })))
            .then(({ ok, d }) => {
                if (!activo) {
                    return;
                }

                if (!ok) {
                    setErrorRec(d.error ?? 'No se pudo obtener el recorrido.');
                    setRecorrido(null);
                } else {
                    setErrorRec(null);
                    setRecorrido(d);
                    setPos(0);
                    setPlaying(false);
                }

                setCargando(false);
            })
            .catch(() => {
                if (activo) {
                    setErrorRec('No se pudo conectar.');
                    setCargando(false);
                }
            });

        return () => {
            activo = false;
        };
    }, [modo, vehRec, preset, fechaDia]);

    // Playback: avanza el índice (setState dentro del callback del intervalo).
    useEffect(() => {
        if (modo !== 'recorrido' || !playing || puntos.length < 2) {
            return;
        }

        const id = setInterval(
            () => {
                setPos((p) => {
                    const next = p + 1;

                    if (next >= puntos.length) {
                        return repeat ? 0 : puntos.length - 1;
                    }

                    return next;
                });
            },
            Math.max(15, 1000 / (PUNTOS_POR_SEG * speed)),
        );

        return () => clearInterval(id);
    }, [modo, playing, speed, repeat, puntos.length]);

    const abrirRecorrido = (id: number) => {
        setVehRec(id);
        setRecorrido(null);
        setErrorRec(null);
        setPos(0);
        setPlaying(false);
        setCargando(true);
        setModo('recorrido');
    };

    const elegirPreset = (value: string) => {
        setPreset(value);
        setCargando(true);
    };

    const elegirFechaDia = (value: string) => {
        setFechaDia(value);
        setPreset('personalizado');
        setCargando(true);
    };

    const volverAVivo = () => {
        setModo('vivo');
        setVehRec(null);
        setPlaying(false);
    };

    const toggle = (id: number) => {
        setSeleccionados((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const ciclarVelocidad = () => {
        setSpeed((s) => {
            const i = VELOCIDADES.indexOf(s);

            return VELOCIDADES[(i + 1) % VELOCIDADES.length];
        });
    };

    return (
        <div className="flex h-full flex-col gap-4 p-4 md:p-6">
            <Head
                title={modo === 'recorrido' ? 'Recorrido' : 'Mapa de flota'}
            />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {modo === 'recorrido' ? 'Recorrido' : 'Mapa de flota'}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {modo === 'recorrido'
                        ? 'Reproducción del movimiento del vehículo por fecha.'
                        : `Mostrando ${visibles.length} de ${marcadores.length} con señal · ${enMovimiento} en movimiento`}
                </p>
            </div>

            {modo === 'vivo' && error && (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Error de ubicación</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {totalConGps === 0 ? (
                <EmptyState
                    icon={<Satellite className="size-6" />}
                    text="Aún no hay vehículos con dispositivo GPS vinculado. Agrega el IMEI a un vehículo para verlo en el mapa."
                />
            ) : (
                <div className="flex min-h-[440px] flex-1 flex-col gap-4 lg:flex-row">
                    <aside className="flex shrink-0 flex-col rounded-xl border border-border bg-card lg:w-72">
                        {modo === 'recorrido' ? (
                            <PanelRecorrido
                                placa={placaRec}
                                preset={preset}
                                fechaDia={fechaDia}
                                recorrido={recorrido}
                                onPreset={elegirPreset}
                                onFechaDia={elegirFechaDia}
                                onVolver={volverAVivo}
                            />
                        ) : (
                            <PanelVivo
                                marcadores={marcadores}
                                seleccionados={seleccionados}
                                onToggle={toggle}
                                onTodos={() =>
                                    setSeleccionados(
                                        new Set(marcadores.map((m) => m.id)),
                                    )
                                }
                                onNinguno={() => setSeleccionados(new Set())}
                                onRecorrido={abrirRecorrido}
                            />
                        )}
                    </aside>

                    <div className="relative isolate z-0 min-h-[360px] flex-1 overflow-hidden rounded-xl border border-border">
                        <MapaLienzo
                            modo={modo}
                            marcadores={visibles}
                            puntos={puntos}
                            pos={pos}
                        />

                        {modo === 'vivo' && visibles.length === 0 && (
                            <div className="absolute inset-0 z-[1000] flex items-center justify-center bg-muted/95 p-6 text-center text-sm text-muted-foreground">
                                <span className="flex items-center gap-2">
                                    <MapPin className="size-4" />
                                    {marcadores.length === 0
                                        ? 'Los vehículos con GPS aún no reportan una posición válida.'
                                        : 'Selecciona al menos un vehículo en la lista para verlo en el mapa.'}
                                </span>
                            </div>
                        )}

                        {modo === 'recorrido' && cargando && (
                            <div className="absolute inset-0 z-[1000] flex items-center justify-center bg-muted">
                                <Spinner />
                            </div>
                        )}

                        {modo === 'recorrido' && !cargando && errorRec && (
                            <div className="absolute inset-0 z-[1000] flex items-center justify-center bg-muted p-6 text-center text-sm text-muted-foreground">
                                {errorRec}
                            </div>
                        )}

                        {modo === 'recorrido' &&
                            !cargando &&
                            !errorRec &&
                            !hayRuta && (
                                <div className="absolute inset-0 z-[1000] flex items-center justify-center bg-muted p-6 text-center text-sm text-muted-foreground">
                                    <span className="flex items-center gap-2">
                                        <MapPin className="size-4" />
                                        No hay recorrido en ese período.
                                    </span>
                                </div>
                            )}

                        {modo === 'recorrido' && hayRuta && (
                            <div className="absolute top-3 left-3 z-[1000] flex flex-col gap-1.5 rounded-xl border border-border bg-card/95 p-2 shadow-lg backdrop-blur">
                                <div className="flex items-center gap-1.5">
                                    <Button
                                        size="icon"
                                        className="size-9 bg-emerald-800 hover:bg-emerald-900"
                                        onClick={() => setPlaying((v) => !v)}
                                        title={
                                            playing ? 'Pausar' : 'Reproducir'
                                        }
                                    >
                                        {playing ? (
                                            <Pause className="size-4" />
                                        ) : (
                                            <Play className="size-4" />
                                        )}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-9 w-14 font-semibold tabular-nums"
                                        onClick={ciclarVelocidad}
                                        title="Velocidad (click para cambiar)"
                                    >
                                        {speed}x
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant={repeat ? 'default' : 'outline'}
                                        className={`size-9 ${repeat ? 'bg-emerald-800 hover:bg-emerald-900' : ''}`}
                                        title="Repetir"
                                        onClick={() => setRepeat((v) => !v)}
                                    >
                                        <Repeat className="size-4" />
                                    </Button>
                                </div>
                                <input
                                    type="range"
                                    min={0}
                                    max={Math.max(0, puntos.length - 1)}
                                    value={pos}
                                    onChange={(e) =>
                                        setPos(Number(e.target.value))
                                    }
                                    className="w-48 accent-emerald-700"
                                />
                                <p className="text-center text-[11px] text-muted-foreground tabular-nums">
                                    {puntos[pos]?.hora ?? ''}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

MapaIndex.layout = {
    breadcrumbs: [{ title: 'Mapa de flota', href: mapa().url }],
};
