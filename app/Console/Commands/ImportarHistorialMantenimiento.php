<?php

namespace App\Console\Commands;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\Vehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Carga los servicios ya hechos desde la hoja «MANTENIMIENTOS EJECUTADOS» del
 * registro de mantenimiento, para que el semáforo arranque sabiendo dónde va
 * cada unidad en vez de en «sin datos».
 *
 * La hoja trae, por unidad, una fila por paquete (X, S, M, L) y en cada mes
 * un grupo FECHA · T/Serv. · KM · PROX. CAMBIO. El servicio se toma de la
 * fila y no de «T/Serv.», que a veces dice «PM» en la fila de la S. Un
 * «RETARDER» suelto no es un paquete del plan: entra como correctiva, que
 * queda en el historial sin mover los contadores.
 *
 * Solo marcas con plan cargado. Volver a correrlo no duplica: una orden de la
 * misma unidad, fecha y km se salta.
 */
#[Signature('mantenimiento:importar-historial
    {archivo : Ruta al .xlsx del registro de mantenimiento}
    {--seco : Solo mostrar qué se importaría, sin guardar nada}')]
#[Description('Importa los mantenimientos ya ejecutados desde el Excel de registro')]
class ImportarHistorialMantenimiento extends Command
{
    private const HOJA = 'MANTENIMIENTOS EJECUTADOS';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error("No existe el archivo {$archivo}.");

            return self::FAILURE;
        }

        $hoja = collect(IOFactory::load($archivo)->getAllSheets())
            ->first(fn (Worksheet $hoja): bool => mb_strtoupper(trim($hoja->getTitle())) === self::HOJA);

        if ($hoja === null) {
            $this->error('El archivo no tiene la hoja «'.self::HOJA.'».');

            return self::FAILURE;
        }

        $planes = PlanMantenimiento::query()->with('servicios')->get()->keyBy('marca');
        $vehiculos = Vehiculo::query()->get(['id', 'placa', 'marca'])
            ->keyBy(fn (Vehiculo $vehiculo): string => self::placaNormalizada($vehiculo->placa));

        $creadas = 0;
        $repetidas = 0;
        /** @var array<string, int> $saltadas */
        $saltadas = [];

        $placa = null;

        DB::beginTransaction();

        foreach ($hoja->getRowIterator(4) as $fila) {
            // Valor y «es fecha» en la misma pasada: PhpSpreadsheet reusa el
            // objeto de celda al recorrer, y guardarlo para después no sirve.
            $valores = [];
            $esFecha = [];

            foreach ($fila->getCellIterator('A', $hoja->getHighestColumn()) as $celda) {
                $valores[] = $celda->getValue();
                $esFecha[] = Date::isDateTime($celda);
            }

            if (filled($valores[1] ?? null)) {
                $placa = self::placaNormalizada((string) $valores[1]);
            }

            $codigo = mb_strtoupper(trim((string) ($valores[4] ?? '')));

            if ($placa === null || $codigo === '') {
                continue;
            }

            foreach ($valores as $indice => $valor) {
                if (! is_numeric($valor) || ! $esFecha[$indice]) {
                    continue;
                }

                $fecha = CarbonImmutable::instance(Date::excelToDateTimeObject((float) $valor))->toDateString();
                $tipoServicio = mb_strtoupper(trim((string) ($valores[$indice + 1] ?? '')));
                $km = is_numeric($valores[$indice + 2] ?? null) ? (int) round((float) $valores[$indice + 2]) : null;

                $vehiculo = $vehiculos->get($placa);
                $plan = $vehiculo?->marca === null ? null : $planes->get(mb_strtoupper(trim($vehiculo->marca)));

                if ($vehiculo === null || $plan === null || $km === null) {
                    $motivo = match (true) {
                        $vehiculo === null => "placa {$placa} no está en el sistema",
                        $plan === null => 'marca '.($vehiculo->marca ?? '?').' sin plan',
                        default => 'sin km',
                    };
                    $saltadas[$motivo] = ($saltadas[$motivo] ?? 0) + 1;

                    continue;
                }

                $retarder = str_contains($tipoServicio, 'RETARDER');
                $servicio = $retarder ? null : $plan->servicios->firstWhere('codigo', $codigo);

                if (! $retarder && $servicio === null) {
                    $motivo = "servicio {$codigo} no está en el plan {$plan->marca}";
                    $saltadas[$motivo] = ($saltadas[$motivo] ?? 0) + 1;

                    continue;
                }

                $existe = OrdenTrabajo::query()
                    ->where('vehiculo_id', $vehiculo->id)
                    ->whereDate('fecha', $fecha)
                    ->where('kilometraje', $km)
                    ->exists();

                if ($existe) {
                    $repetidas++;

                    continue;
                }

                OrdenTrabajo::create([
                    'vehiculo_id' => $vehiculo->id,
                    'tipo' => $retarder ? TipoOrdenTrabajo::Correctiva : TipoOrdenTrabajo::Preventiva,
                    'servicio_mantenimiento_id' => $servicio?->id,
                    'estado' => EstadoOrdenTrabajo::Finalizada,
                    'fecha' => $fecha,
                    'kilometraje' => $km,
                    'trabajo' => $retarder ? 'Servicio de retarder' : $servicio->nombre,
                    'observacion' => 'Importado del registro de mantenimiento (Excel).',
                ]);

                $creadas++;
            }
        }

        if ($this->option('seco')) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        $this->info(($this->option('seco') ? '[En seco] ' : '')."Órdenes importadas: {$creadas}. Ya estaban: {$repetidas}.");

        foreach ($saltadas as $motivo => $cantidad) {
            $this->warn("Saltadas ({$motivo}): {$cantidad}");
        }

        return self::SUCCESS;
    }

    private static function placaNormalizada(string $placa): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($placa));
    }
}
