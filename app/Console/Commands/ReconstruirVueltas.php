<?php

namespace App\Console\Commands;

use App\Models\Vehiculo;
use App\Services\ReconstructorVueltas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('transpaty:reconstruir-vueltas
    {--placa= : Solo la unidad con esta placa}
    {--dry-run : Muestra cuántas vueltas saldrían, sin guardar nada}')]
#[Description('Arma las vueltas (ida y regreso) de cada unidad a partir de sus GR. Rehace solo las vueltas del historial: las programadas a mano y sus GR no se tocan.')]
class ReconstruirVueltas extends Command
{
    public function __construct(private readonly ReconstructorVueltas $reconstructor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tractoId = null;

        if ($placa = $this->option('placa')) {
            $tracto = Vehiculo::query()->wherePlacaLike((string) $placa)->first();

            if ($tracto === null) {
                $this->error("No hay ninguna unidad con la placa {$placa}.");

                return self::FAILURE;
            }

            $tractoId = $tracto->id;
        }

        $seco = (bool) $this->option('dry-run');

        // En dry-run se arma todo de verdad y se deshace al final: así el
        // conteo es exactamente el que saldría.
        DB::beginTransaction();

        try {
            $resumen = $this->reconstructor->reconstruir($tractoId);
        } catch (Throwable $error) {
            DB::rollBack();

            throw $error;
        }

        $seco ? DB::rollBack() : DB::commit();

        $this->info(
            ($seco ? '[dry-run] ' : '')
            ."Vueltas: {$resumen['vueltas']} · paradas: {$resumen['paradas']}"
            ." (vacíos: {$resumen['vacios']}) · GR asignadas: {$resumen['guias']}"
        );

        return self::SUCCESS;
    }
}
