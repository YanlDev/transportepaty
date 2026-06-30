<?php

namespace App\Services\Tracksolid;

use Illuminate\Support\Facades\Cache;

/**
 * Obtiene y cachea brevemente las últimas ubicaciones de los dispositivos,
 * para que el polling del mapa y la pantalla del vehículo no excedan el
 * rate limit de la Open API.
 */
class UbicacionService
{
    /** Segundos que se cachea una respuesta de ubicación. */
    private const CACHE_TTL = 25;

    public function __construct(private readonly TracksolidClient $client) {}

    /**
     * Últimas ubicaciones indexadas por IMEI.
     *
     * @param  list<string>  $imeis
     * @return array<string, TracksolidLocation>
     *
     * @throws TracksolidException
     */
    public function obtener(array $imeis): array
    {
        if ($imeis === []) {
            return [];
        }

        sort($imeis);

        $raw = Cache::remember(
            'tracksolid:loc:'.md5(implode(',', $imeis)),
            now()->addSeconds(self::CACHE_TTL),
            fn (): array => $this->client->latestLocations($imeis)->all(),
        );

        $ubicaciones = [];

        foreach ($raw as $fila) {
            $ubicacion = TracksolidLocation::fromArray($fila);
            $ubicaciones[$ubicacion->imei()] = $ubicacion;
        }

        return $ubicaciones;
    }
}
