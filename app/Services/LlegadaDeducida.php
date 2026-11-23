<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Models\User;
use App\Models\Viaje;
use App\Models\Vuelta;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * La llegada a la base que nadie registró: si la unidad vuelve a salir es
 * que llegó, y el ticket anterior queda con la llegada el día antes de la
 * nueva salida. Va sin kilometraje (nadie lo anotó) y marcada como deducida;
 * una persona la puede corregir después.
 */
class LlegadaDeducida
{
    /**
     * Los tickets en ruta de la unidad que salieron antes de esa salida
     * quedan con su llegada deducida.
     *
     * @return list<Vuelta> Los que quedaron con llegada.
     */
    public function alSalirDeNuevo(int $tractoId, CarbonInterface $salida, ?User $usuario = null, ?int $exceptoId = null): array
    {
        return array_values(Vuelta::query()
            ->where('tracto_id', $tractoId)
            ->where('estado', EstadoVuelta::EnCurso)
            ->whereNull('llegada_el')
            ->whereDate('inicio', '<', $salida->toDateString())
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->get()
            ->each(fn (Vuelta $anterior) => $this->anotar($anterior, $salida, $usuario))
            ->all());
    }

    /**
     * El día en que se da por llegado: el anterior a la nueva salida, nunca
     * futuro ni anterior a la última GR o a la salida del propio ticket.
     */
    public function dia(Vuelta $anterior, CarbonInterface $salida): Carbon
    {
        $dia = Carbon::parse($salida->toDateString())->subDay();
        $hoy = Carbon::parse(RelojOperativo::fechaDeHoy()->toDateString());

        if ($dia->gt($hoy)) {
            $dia = $hoy;
        }

        $ultimaGuia = Viaje::query()
            ->whereIn('parada_vuelta_id', $anterior->paradas()->select('id'))
            ->max('fecha_traslado');

        foreach ([$ultimaGuia, $anterior->inicio?->toDateString()] as $minimo) {
            if ($minimo !== null && $dia->lt(Carbon::parse($minimo)->startOfDay())) {
                $dia = Carbon::parse($minimo)->startOfDay();
            }
        }

        return $dia;
    }

    /**
     * El día en que la unidad volvió a salir después de ese ticket: la
     * salida de su siguiente ticket, si tiene alguno.
     */
    public function siguienteSalida(Vuelta $ticket): ?Carbon
    {
        if ($ticket->inicio === null) {
            return null;
        }

        $salida = Vuelta::query()
            ->where('tracto_id', $ticket->tracto_id)
            ->whereKeyNot($ticket->id)
            ->where('estado', '!=', EstadoVuelta::Anulada)
            ->whereDate('inicio', '>', $ticket->inicio->toDateString())
            ->min('inicio');

        return $salida === null ? null : Carbon::parse($salida);
    }

    public function anotar(Vuelta $anterior, CarbonInterface $salida, ?User $usuario = null): Vuelta
    {
        $anterior->update([
            'llegada_el' => $this->dia($anterior, $salida)->toDateString(),
            'llegada_por' => $usuario?->id,
            'llegada_deducida' => true,
        ]);

        return $anterior;
    }
}
