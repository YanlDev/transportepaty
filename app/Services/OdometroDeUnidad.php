<?php

namespace App\Services;

use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;

/**
 * El kilometraje que se anota en el circuito del ticket (OK de taller,
 * despacho de combustible, llegada a la base) es también una lectura para
 * mantenimiento: así el próximo servicio se calcula con el último km real.
 *
 * Cada paso anota una sola lectura, identificada por su origen
 * («abastecimiento:12», «llegada:5», «salida:5»): corregir un km mal
 * tipeado corrige esa lectura, no suma otra que la deje para siempre.
 */
class OdometroDeUnidad
{
    public static function deDespacho(int $abastecimientoId): string
    {
        return "abastecimiento:{$abastecimientoId}";
    }

    public static function deLlegada(int $vueltaId): string
    {
        return "llegada:{$vueltaId}";
    }

    public static function deSalida(int $vueltaId): string
    {
        return "salida:{$vueltaId}";
    }

    /**
     * Por qué el km no se acepta, o null si está bien. El odómetro no
     * retrocede: un km menor que el último conocido es un error de tipeo o
     * de placa. La lectura que se está corrigiendo (su origen) no cuenta.
     */
    public function error(Vehiculo $vehiculo, int $kilometraje, ?string $origen = null): ?string
    {
        $ultimo = max(
            (int) LecturaKilometraje::query()
                ->where('vehiculo_id', $vehiculo->id)
                ->when($origen !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('origen')->orWhere('origen', '!=', $origen)))
                ->max('kilometraje'),
            (int) OrdenTrabajo::query()->vigentes()->where('vehiculo_id', $vehiculo->id)->max('kilometraje'),
        );

        return $ultimo > 0 && $kilometraje < $ultimo
            ? 'El último km de '.$vehiculo->placa.' es '.number_format($ultimo, 0, '.', ',').': el nuevo no puede ser menor.'
            : null;
    }

    /**
     * Anota (o corrige) la lectura de ese paso del ticket. Sin km, la que
     * había se quita: el km se borró porque estaba mal.
     */
    public function anotar(int $vehiculoId, ?int $kilometraje, ?User $usuario, string $observacion, string $origen): void
    {
        if ($kilometraje === null) {
            LecturaKilometraje::query()->where('origen', $origen)->first()?->delete();

            return;
        }

        LecturaKilometraje::query()->updateOrCreate(['origen' => $origen], [
            'vehiculo_id' => $vehiculoId,
            'kilometraje' => $kilometraje,
            'leido_en' => now(),
            'user_id' => $usuario?->id,
            'observacion' => $observacion,
        ]);
    }
}
