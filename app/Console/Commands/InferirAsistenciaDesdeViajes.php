<?php

namespace App\Console\Commands;

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Viaje;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Marca como "asistencia" cada día en que un conductor tiene una GR
 * (`viajes.fecha_traslado`) registrada: si manejó ese día, trabajó. No pisa
 * ningún día ya marcado (a mano o por una corrida anterior de este mismo
 * comando), así que correrlo de nuevo sobre el mismo rango no duplica nada.
 */
#[Signature('transpaty:inferir-asistencia
    {--desde= : Fecha desde (Y-m-d), por defecto hace 60 días}
    {--hasta= : Fecha hasta (Y-m-d), por defecto hoy}
    {--dry-run : Solo muestra qué se crearía, sin escribir nada}')]
#[Description('Infiere asistencia de conductores a partir de las GR (viajes) ya importadas.')]
class InferirAsistenciaDesdeViajes extends Command
{
    public function handle(): int
    {
        $desde = $this->fecha($this->option('desde')) ?? Carbon::today()->subDays(60);
        $hasta = $this->fecha($this->option('hasta')) ?? Carbon::today();
        $seco = (bool) $this->option('dry-run');

        $viajes = Viaje::query()
            ->whereNotNull('conductor_id')
            ->whereBetween('fecha_traslado', [$desde->toDateString(), $hasta->toDateString()])
            ->get(['conductor_id', 'fecha_traslado', 'numero_gr'])
            ->unique(fn (Viaje $viaje): string => "{$viaje->conductor_id}|{$viaje->fecha_traslado->toDateString()}");

        $creadas = $yaExistian = 0;

        foreach ($viajes as $viaje) {
            $existe = Asistencia::query()
                ->where('conductor_id', $viaje->conductor_id)
                ->where('fecha', $viaje->fecha_traslado->toDateString())
                ->exists();

            if ($existe) {
                $yaExistian++;

                continue;
            }

            $creadas++;

            if ($seco) {
                continue;
            }

            Asistencia::query()->create([
                'conductor_id' => $viaje->conductor_id,
                'fecha' => $viaje->fecha_traslado->toDateString(),
                'estado' => EstadoAsistencia::Asistencia,
                'observaciones' => "Inferido de GR {$viaje->numero_gr}",
            ]);
        }

        $this->info(
            ($seco ? '[dry-run] ' : '')
            ."Filas creadas: {$creadas} · ya existían: {$yaExistian}"
        );

        return self::SUCCESS;
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }
}
