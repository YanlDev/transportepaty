import 'leaflet/dist/leaflet.css';
import type { Map as LeafletMap } from 'leaflet';
import { useRef } from 'react';
import { CircleMarker, MapContainer, Popup, TileLayer } from 'react-leaflet';
import type { PuntoMapa } from '@/types/fleet';

type Props = {
    puntos: PuntoMapa[];
};

/** Centro aproximado del corredor, entre Nazca y la costa de Arequipa. */
const CENTRO: [number, number] = [-15.2, -73.5];

/**
 * El radio crece con la cantidad de unidades pero muy despacio, para que un
 * punto con veinte no tape media costa.
 */
const radioDe = (total: number): number => 8 + Math.min(total, 25) * 0.6;

/**
 * Última posición reportada de la flota. No es seguimiento en vivo: cada punto
 * es donde quedó la unidad en su último reporte, que es lo que hay sin GPS.
 *
 * Los marcadores se agrupan por ubicación en vez de dibujar uno por unidad,
 * porque sesenta unidades sobre veinte puntos se encimarían hasta ser ilegibles.
 */
export function MapaFlota({ puntos }: Props) {
    const mapa = useRef<LeafletMap | null>(null);

    if (puntos.length === 0) {
        return (
            <div className="grid h-96 place-items-center rounded-xl border border-dashed text-center">
                <p className="max-w-sm text-sm text-muted-foreground">
                    Ninguna unidad tiene todavía una ubicación con posición
                    conocida.
                </p>
            </div>
        );
    }

    return (
        <div className="h-96 overflow-hidden rounded-xl border md:h-[32rem]">
            <MapContainer
                ref={mapa}
                center={CENTRO}
                zoom={6}
                scrollWheelZoom={false}
                className="h-full w-full"
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />

                {puntos.map((punto) => (
                    <CircleMarker
                        key={punto.id}
                        center={[punto.latitud, punto.longitud]}
                        radius={radioDe(punto.total)}
                        pathOptions={{
                            color: punto.es_zona_base ? '#0f766e' : '#b45309',
                            fillColor: punto.es_zona_base
                                ? '#14b8a6'
                                : '#f59e0b',
                            fillOpacity: 0.65,
                            weight: 2,
                        }}
                    >
                        <Popup>
                            <p className="font-semibold">
                                {punto.nombre} · {punto.total}{' '}
                                {punto.total === 1 ? 'unidad' : 'unidades'}
                            </p>
                            <ul className="mt-1 space-y-0.5">
                                {punto.unidades.map((unidad) => (
                                    <li key={unidad.id}>
                                        <span className="font-medium">
                                            {unidad.placa}
                                        </span>
                                        {unidad.tipo_carga_label &&
                                            ` · ${unidad.tipo_carga_label}`}
                                        {unidad.destino &&
                                            ` → ${unidad.destino}`}
                                    </li>
                                ))}
                            </ul>
                        </Popup>
                    </CircleMarker>
                ))}
            </MapContainer>
        </div>
    );
}
