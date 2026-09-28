<?php

namespace App\Console\Commands;

use App\Services\Sunat\EmisionGre;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('transpaty:registrar-gre-emitida {numeros* : Números de GR-transportista de Paty ya emitidas (ej. EG03-00012624)}')]
#[Description('Baja de SUNAT el PDF de GR-transportista ya emitidas y las registra como viajes, para las que se emitieron pero no llegaron a Viajes.')]
class RegistrarGreEmitida extends Command
{
    public function handle(EmisionGre $emision): int
    {
        $fallidas = 0;

        foreach ((array) $this->argument('numeros') as $numero) {
            try {
                $viaje = $emision->registrarEmitida((string) $numero);
            } catch (RuntimeException $error) {
                $viaje = null;
                $this->error("{$numero}: {$error->getMessage()}");
            }

            if ($viaje === null) {
                $fallidas++;
                $this->warn("{$numero}: no se pudo registrar (SUNAT no dio el PDF o no se reconoció).");

                continue;
            }

            $this->info("{$numero}: registrado como viaje #{$viaje->id} ({$viaje->placa_tracto}, {$viaje->fecha_traslado->format('d/m/Y')}).");
        }

        return $fallidas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
