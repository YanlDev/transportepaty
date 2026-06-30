import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, History, RefreshCw, Route, Video } from 'lucide-react';
import { useCallback, useState } from 'react';
import { camara } from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import vehiculos, {
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type Modo = 'vivo' | 'historico';
type Canal = 1 | 2;

// La consola de JIMI es grande; la escalamos para que entre completa.
const ESCALA_FRAME = 0.85;

type Props = {
    vehiculo: { id: number; placa: string; marca: string; modelo: string };
    urlInicial: string | null;
    vehiculosGps: { id: number; placa: string }[];
};

export default function VehiculoCamara({
    vehiculo,
    urlInicial,
    vehiculosGps,
}: Props) {
    const [vehiculoId, setVehiculoId] = useState(vehiculo.id);
    const [modo, setModo] = useState<Modo>('vivo');
    const [canal, setCanal] = useState<Canal>(1);
    const [url, setUrl] = useState<string | null>(urlInicial);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState<string | null>(
        urlInicial ? null : 'La cámara no está disponible en este momento.',
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos.index().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
            { title: 'Cámaras', href: show(vehiculo.id).url },
        ],
    });

    const cargar = useCallback(async (vehId: number, m: Modo, ch: Canal) => {
        setUrl(null);
        setError(null);
        setCargando(true);

        const query =
            m === 'historico'
                ? { modo: 'historico' }
                : { modo: 'vivo', channel: ch };

        try {
            const respuesta = await fetch(camara(vehId, { query }).url, {
                headers: { Accept: 'application/json' },
            });
            const datos = await respuesta.json();

            if (!respuesta.ok) {
                setError(datos.error ?? 'No se pudo abrir la cámara.');
            } else {
                setUrl(datos.url);
            }
        } catch {
            setError('No se pudo conectar con la cámara.');
        } finally {
            setCargando(false);
        }
    }, []);

    const placaActiva =
        vehiculosGps.find((v) => v.id === vehiculoId)?.placa ?? vehiculo.placa;

    return (
        <div className="flex h-full flex-col gap-4 p-4 md:p-6">
            <Head title={`Cámaras · ${vehiculo.placa}`} />

            <div className="flex items-center gap-3">
                <Button asChild variant="outline" size="icon" title="Volver">
                    <Link href={show(vehiculo.id)} aria-label="Volver">
                        <ArrowLeft className="size-4" />
                    </Link>
                </Button>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Cámaras
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Dashcam · {placaActiva}
                    </p>
                </div>
            </div>

            <div className="flex min-h-[600px] flex-1 flex-col gap-4 lg:flex-row">
                {/* Panel de control */}
                <aside className="flex shrink-0 flex-col gap-3 rounded-xl border border-border bg-card p-3 lg:w-64">
                    <div>
                        <label className="mb-1.5 block px-1 text-xs font-medium text-muted-foreground">
                            Vehículo
                        </label>
                        <Select
                            value={String(vehiculoId)}
                            onValueChange={(value) => {
                                const id = Number(value);
                                setVehiculoId(id);
                                cargar(id, modo, canal);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {vehiculosGps.map((v) => (
                                    <SelectItem key={v.id} value={String(v.id)}>
                                        {v.placa}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex flex-col gap-2 border-t border-border pt-3">
                        <ModoBoton
                            activo={modo === 'vivo'}
                            icon={<Video className="size-4" />}
                            titulo="En vivo"
                            onClick={() => {
                                setModo('vivo');
                                cargar(vehiculoId, 'vivo', canal);
                            }}
                            disabled={cargando}
                        />

                        {modo === 'vivo' && (
                            <div className="grid grid-cols-2 gap-2 pl-1">
                                <CanalBoton
                                    activo={canal === 1}
                                    icon={<Route className="size-3.5" />}
                                    label="Carretera"
                                    onClick={() => {
                                        setCanal(1);
                                        cargar(vehiculoId, 'vivo', 1);
                                    }}
                                    disabled={cargando}
                                />
                                <CanalBoton
                                    activo={canal === 2}
                                    icon={<Video className="size-3.5" />}
                                    label="Cabina"
                                    onClick={() => {
                                        setCanal(2);
                                        cargar(vehiculoId, 'vivo', 2);
                                    }}
                                    disabled={cargando}
                                />
                            </div>
                        )}

                        <ModoBoton
                            activo={modo === 'historico'}
                            icon={<History className="size-4" />}
                            titulo="Video histórico"
                            onClick={() => {
                                setModo('historico');
                                cargar(vehiculoId, 'historico', canal);
                            }}
                            disabled={cargando}
                        />
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-auto w-full"
                        onClick={() => cargar(vehiculoId, modo, canal)}
                        disabled={cargando}
                    >
                        <RefreshCw className="size-4" />
                        Actualizar
                    </Button>
                </aside>

                {/* Video */}
                <div className="relative min-h-[560px] flex-1 overflow-hidden rounded-xl border border-border bg-zinc-950">
                    {cargando ? (
                        <Estado texto="Conectando con la cámara…" spinner />
                    ) : error ? (
                        <Estado texto={error} />
                    ) : url ? (
                        <iframe
                            key={url}
                            src={url}
                            title="Cámara"
                            className="border-0"
                            style={{
                                width: `${100 / ESCALA_FRAME}%`,
                                height: `${100 / ESCALA_FRAME}%`,
                                transform: `scale(${ESCALA_FRAME})`,
                                transformOrigin: 'top left',
                            }}
                            allow="autoplay; fullscreen; encrypted-media; microphone"
                            allowFullScreen
                        />
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function ModoBoton({
    activo,
    icon,
    titulo,
    onClick,
    disabled,
}: {
    activo: boolean;
    icon: React.ReactNode;
    titulo: string;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={
                activo
                    ? 'flex items-center gap-2.5 rounded-lg border border-emerald-600/30 bg-emerald-50 px-3 py-2.5 text-left text-sm font-semibold text-emerald-900'
                    : 'flex items-center gap-2.5 rounded-lg border border-transparent px-3 py-2.5 text-left text-sm font-medium text-foreground transition-colors hover:bg-muted'
            }
        >
            <span
                className={
                    activo ? 'text-emerald-700' : 'text-muted-foreground'
                }
            >
                {icon}
            </span>
            {titulo}
        </button>
    );
}

function CanalBoton({
    activo,
    icon,
    label,
    onClick,
    disabled,
}: {
    activo: boolean;
    icon: React.ReactNode;
    label: string;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <Button
            variant={activo ? 'default' : 'outline'}
            size="sm"
            className={activo ? 'bg-emerald-800 hover:bg-emerald-900' : ''}
            onClick={onClick}
            disabled={disabled}
        >
            {icon}
            {label}
        </Button>
    );
}

function Estado({ texto, spinner }: { texto: string; spinner?: boolean }) {
    return (
        <div className="flex size-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-zinc-400">
            {spinner ? <Spinner /> : <Video className="size-6" />}
            <p className="max-w-xs">{texto}</p>
        </div>
    );
}
