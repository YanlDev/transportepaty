import { Link } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Video } from 'lucide-react';
import { useEffect } from 'react';
import {
    MapContainer,
    Marker,
    Polyline,
    Popup,
    TileLayer,
    Tooltip,
    useMap,
    ZoomControl,
} from 'react-leaflet';
import { camaraPage as camaras } from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import { estadoGpsInfo } from '@/types/fleet';
import type { MarcadorVehiculo, PuntoRecorrido } from '@/types/fleet';

/** Center of Peru, used as a fallback when there is nothing to show. */
const CENTRO_PERU: [number, number] = [-9.19, -75.0152];

/**
 * Top-down car marker for the live fleet, colored by movement state and
 * rotated to the device heading (only when moving).
 */
function iconoAutoFlota(
    color: string,
    rumbo: number,
    enMovimiento: boolean,
): L.DivIcon {
    const rotacion = enMovimiento ? rumbo : 0;

    return L.divIcon({
        className: '',
        html: `<div style="transform: rotate(${rotacion}deg); transform-origin: center;">
            <svg width="34" height="34" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="5" width="14" height="24" rx="6" fill="${color}" stroke="#ffffff" stroke-width="2"/>
                <rect x="12.5" y="8" width="9" height="6" rx="2" fill="#ffffff" fill-opacity="0.9"/>
                <rect x="12.5" y="19.5" width="9" height="5" rx="2" fill="#ffffff" fill-opacity="0.45"/>
            </svg>
        </div>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        popupAnchor: [0, -16],
    });
}

/** Small dot used to mark the start of a route. */
function iconoPunto(color: string): L.DivIcon {
    return L.divIcon({
        className: '',
        html: `<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="7" fill="${color}" stroke="#fff" stroke-width="3"/>
        </svg>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    });
}

/** Arrow marker for the moving head during route playback. */
function iconoAutoRecorrido(rumbo: number): L.DivIcon {
    return L.divIcon({
        className: '',
        html: `<div style="transform: rotate(${rumbo}deg); transform-origin: center;">
            <svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
                <circle cx="15" cy="15" r="13" fill="#3b82f6" fill-opacity="0.25"/>
                <path d="M15 4 L22 24 L15 19 L8 24 Z" fill="#2563eb" stroke="#fff" stroke-width="1.5"/>
            </svg>
        </div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

/** Re-fits the view to the live markers whenever the set changes. */
function AjustarVistaFlota({ marcadores }: { marcadores: MarcadorVehiculo[] }) {
    const map = useMap();

    useEffect(() => {
        const puntos = marcadores
            .filter((m) => m.lat !== null && m.lng !== null)
            .map((m) => [m.lat as number, m.lng as number] as [number, number]);

        if (puntos.length === 1) {
            map.setView(puntos[0], 15);
        } else if (puntos.length > 1) {
            map.fitBounds(puntos, { padding: [50, 50], maxZoom: 15 });
        }
    }, [map, marcadores]);

    return null;
}

/** Re-fits the view to the route whenever the route itself changes. */
function AjustarVistaRuta({ ruta }: { ruta: [number, number][] }) {
    const map = useMap();

    useEffect(() => {
        if (ruta.length === 1) {
            map.setView(ruta[0], 16);
        } else if (ruta.length > 1) {
            map.fitBounds(ruta, { padding: [40, 40], maxZoom: 17 });
        }
        // Solo al cambiar la ruta (no en cada frame del playback).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ruta.length]);

    return null;
}

function CapaFlota({ marcadores }: { marcadores: MarcadorVehiculo[] }) {
    return (
        <>
            <AjustarVistaFlota marcadores={marcadores} />

            {marcadores.map((marcador) => {
                if (marcador.lat === null || marcador.lng === null) {
                    return null;
                }

                const info = estadoGpsInfo(marcador.estado);

                return (
                    <Marker
                        key={marcador.id}
                        position={[marcador.lat, marcador.lng]}
                        icon={iconoAutoFlota(
                            info.color,
                            marcador.rumbo,
                            marcador.estado === 'en_movimiento',
                        )}
                    >
                        <Tooltip
                            permanent
                            direction="top"
                            offset={[0, -16]}
                            className="placa-tooltip"
                        >
                            {marcador.placa}
                        </Tooltip>
                        <Popup>
                            <div className="space-y-1.5">
                                <div className="flex items-center gap-2">
                                    <Link
                                        href={show(marcador.id)}
                                        className="font-mono text-sm font-semibold text-emerald-800 hover:underline"
                                    >
                                        {marcador.placa}
                                    </Link>
                                    <span
                                        className={`rounded-full px-1.5 py-0.5 text-[10px] font-medium ${info.badge}`}
                                    >
                                        {info.label}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {marcador.marca} {marcador.modelo}
                                    {marcador.sucursal
                                        ? ` · ${marcador.sucursal}`
                                        : ''}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {marcador.velocidad} km/h
                                    {marcador.fecha_gps
                                        ? ` · ${marcador.fecha_gps}`
                                        : ''}
                                </p>
                                <div className="flex items-center gap-3 pt-0.5">
                                    <Link
                                        href={show(marcador.id)}
                                        className="text-xs font-medium text-emerald-800 hover:underline"
                                    >
                                        Ver detalle →
                                    </Link>
                                    <Link
                                        href={camaras(marcador.id)}
                                        className="inline-flex items-center gap-1 text-xs font-medium text-emerald-800 hover:underline"
                                    >
                                        <Video className="size-3" />
                                        Cámara
                                    </Link>
                                </div>
                            </div>
                        </Popup>
                    </Marker>
                );
            })}
        </>
    );
}

function CapaRecorrido({
    puntos,
    pos,
}: {
    puntos: PuntoRecorrido[];
    pos: number;
}) {
    const ruta = puntos.map((p) => [p.lat, p.lng] as [number, number]);
    const indice = Math.min(Math.max(0, Math.floor(pos)), puntos.length - 1);
    const recorrido = ruta.slice(0, indice + 1);
    const actual = puntos[indice];
    const inicio = puntos[0];

    return (
        <>
            <AjustarVistaRuta ruta={ruta} />

            {ruta.length > 1 && (
                <Polyline
                    positions={ruta}
                    pathOptions={{ color: '#93c5fd', weight: 4, opacity: 0.7 }}
                />
            )}
            {recorrido.length > 1 && (
                <Polyline
                    positions={recorrido}
                    pathOptions={{ color: '#2563eb', weight: 5, opacity: 0.95 }}
                />
            )}

            {inicio && (
                <Marker
                    position={[inicio.lat, inicio.lng]}
                    icon={iconoPunto('#10b981')}
                />
            )}
            {actual && (
                <Marker
                    position={[actual.lat, actual.lng]}
                    icon={iconoAutoRecorrido(actual.rumbo)}
                    zIndexOffset={1000}
                />
            )}
        </>
    );
}

type Props = {
    modo: 'vivo' | 'recorrido';
    marcadores: MarcadorVehiculo[];
    puntos: PuntoRecorrido[];
    pos: number;
    className?: string;
};

/**
 * Single persistent Leaflet canvas shared by both map modes. The map instance
 * is mounted once; switching `modo` only swaps the overlay layers (live fleet
 * markers vs. route playback), so Leaflet never re-initializes.
 */
export default function MapaLienzoLeaflet({
    modo,
    marcadores,
    puntos,
    pos,
    className,
}: Props) {
    return (
        <MapContainer
            center={CENTRO_PERU}
            zoom={5}
            scrollWheelZoom
            zoomControl={false}
            className={className ?? 'h-full w-full'}
        >
            <ZoomControl position="topright" />
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />

            {modo === 'vivo' ? (
                <CapaFlota marcadores={marcadores} />
            ) : (
                <CapaRecorrido puntos={puntos} pos={pos} />
            )}
        </MapContainer>
    );
}
