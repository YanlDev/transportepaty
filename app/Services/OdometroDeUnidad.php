<?php

namespace App\Services;

use App\Models\LecturaKilometraje;
use App\Models\User;
use App\Models\Vehiculo;

/**
 * El kilometraje que se anota en el circuito del ticket (OK de taller,
 * despacho de combustible, llegada a la base) es también una lectura para
 * mantenimiento: así el próximo servicio se calcula con el último km real.
 */
class OdometroDeUnidad
{
    public function __construct(private readonly ProximoMantenimiento $mantenimiento) {}

    /**
     * Por qué el km no se acepta, o null si está bien. El odómetro no
     * retrocede: un km menor que el último conocido es un error de tipeo o
     * de placa.
     */
    public function error(Vehiculo $vehiculo, int $kilometraje): ?string
    {
        $ultimo = $this->mantenimiento->paraVehiculo($vehiculo)['km_actual'];

        return $ultimo !== null && $kilometraje < $ultimo
            ? 'El último km de '.$vehiculo->placa.' es '.number_format($ultimo, 0, '.', ',').': el nuevo no puede ser menor.'
            : null;
    }

    public function anotar(int $vehiculoId, int $kilometraje, ?User $usuario, string $observacion): void
    {
        LecturaKilometraje::query()->create([
            'vehiculo_id' => $vehiculoId,
            'kilometraje' => $kilometraje,
            'leido_en' => now(),
            'user_id' => $usuario?->id,
            'observacion' => $observacion,
        ]);
    }
}
