<?php

namespace App\Console\Commands\Tracksolid;

use App\Services\Tracksolid\TracksolidClient;
use App\Services\Tracksolid\TracksolidException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tracksolid:devices')]
#[Description('Lista los dispositivos GPS de la cuenta Tracksolid (prueba de conexión).')]
class TracksolidDevicesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TracksolidClient $tracksolid): int
    {
        try {
            $dispositivos = $tracksolid->listDevices();
        } catch (TracksolidException $e) {
            $this->components->error("Tracksolid [{$e->apiCode}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($dispositivos->isEmpty()) {
            $this->components->warn('No se encontraron dispositivos en la cuenta.');

            return self::SUCCESS;
        }

        $this->table(
            ['IMEI', 'Modelo', 'Placa', 'VIN', 'Conductor', 'Activo'],
            $dispositivos->map(fn (array $d): array => [
                $d['imei'] ?? '',
                $d['mcType'] ?? '',
                $d['vehicleNumber'] ?? '',
                $d['carFrame'] ?? '',
                $d['driverName'] ?? '',
                ($d['enabledFlag'] ?? 0) ? 'Sí' : 'No',
            ])->all(),
        );

        $this->components->info("{$dispositivos->count()} dispositivo(s) encontrado(s).");

        return self::SUCCESS;
    }
}
