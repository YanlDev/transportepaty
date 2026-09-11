<?php

namespace App\Console\Commands;

use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Vuelca a disco el expediente documental que vive en la base: una carpeta
 * "VEHICULOS" con una subcarpeta por placa y, dentro, el archivo de cada
 * documento nombrado `<tipo>_<placa>[_vence-YYYY-MM-DD].<ext>`, para poder
 * revisarlo desde el explorador de archivos (OneDrive) sin entrar al sistema.
 *
 * Solo escribe: nunca borra ni reescribe lo que ya está en la carpeta destino
 * salvo que se pase `--forzar`.
 */
#[Signature('transpaty:exportar-documentos-vehiculos
    {ruta : Carpeta raíz donde se creará la subcarpeta "VEHICULOS" (ej. la carpeta OneDrive del cliente)}
    {--forzar : Reescribe los archivos que ya existen en el destino}
    {--dry-run : Solo muestra qué se copiaría, sin escribir nada}')]
#[Description('Exporta a "<ruta>/VEHICULOS/<placa>/" los documentos (SOAT, TUC, MATPEL, etc.) cargados en el sistema para cada tracto y carreta.')]
class ExportarDocumentosVehiculos extends Command
{
    private const CARPETA = 'VEHICULOS';

    public function handle(): int
    {
        $ruta = rtrim((string) $this->argument('ruta'), '/\\');

        if (! is_dir($ruta)) {
            $this->error("La ruta no existe: {$ruta}");

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');
        $destinoRaiz = $ruta.DIRECTORY_SEPARATOR.self::CARPETA;

        if (! $seco && ! is_dir($destinoRaiz) && ! mkdir($destinoRaiz, 0775, true) && ! is_dir($destinoRaiz)) {
            $this->error("No se pudo crear la carpeta: {$destinoRaiz}");

            return self::FAILURE;
        }

        $copiados = $omitidos = $sinArchivo = 0;

        $vehiculos = Vehiculo::query()->with(['documentos.media'])->orderBy('placa')->get();

        foreach ($vehiculos as $vehiculo) {
            $documentos = $vehiculo->documentos->sortBy(fn (VehiculoDocumento $documento): string => $documento->tipo->value);

            if ($documentos->isEmpty()) {
                continue;
            }

            $this->line("<info>{$vehiculo->placa}</info> ({$vehiculo->tipo->label()})");
            $destinoPlaca = $destinoRaiz.DIRECTORY_SEPARATOR.$vehiculo->placa;
            $usados = [];

            foreach ($documentos as $documento) {
                $media = $documento->getFirstMedia('archivo');

                if ($media === null) {
                    $sinArchivo++;
                    $this->line("  ! {$documento->tipo->value} → sin archivo cargado");

                    continue;
                }

                $nombre = $this->nombreDeArchivo($documento, $vehiculo->placa, $media->file_name, $usados);
                $usados[] = $nombre;
                $destinoArchivo = $destinoPlaca.DIRECTORY_SEPARATOR.$nombre;

                if (! $forzar && is_file($destinoArchivo)) {
                    $omitidos++;
                    $this->line("  = {$nombre}");

                    continue;
                }

                if (! $seco) {
                    if (! is_dir($destinoPlaca) && ! mkdir($destinoPlaca, 0775, true) && ! is_dir($destinoPlaca)) {
                        $this->error("  No se pudo crear la carpeta: {$destinoPlaca}");

                        return self::FAILURE;
                    }

                    copy($media->getPath(), $destinoArchivo);
                }

                $copiados++;
                $this->line("  + {$nombre}");
            }
        }

        $this->newLine();
        $this->info(
            ($seco ? '[dry-run] ' : '')
            ."Copiados: {$copiados} · ya estaban: {$omitidos} · sin archivo en el sistema: {$sinArchivo}"
        );
        $this->line("Destino: {$destinoRaiz}");

        return self::SUCCESS;
    }

    /**
     * `soat_VBH-902_vence-2027-02-15.pdf`: el tipo va primero para que el
     * listado de la carpeta quede agrupado por documento, la placa se repite
     * para que el archivo siga siendo identificable si alguien lo saca de su
     * carpeta, y el vencimiento —cuando lo hay— se ve sin abrir el PDF.
     *
     * @param  array<int, string>  $usados  Nombres ya emitidos para esta placa, para desempatar duplicados del mismo tipo.
     */
    private function nombreDeArchivo(VehiculoDocumento $documento, string $placa, string $archivoOriginal, array $usados): string
    {
        $extension = strtolower(pathinfo($archivoOriginal, PATHINFO_EXTENSION)) ?: 'pdf';

        $partes = [$documento->tipo->value, $placa];

        if ($documento->nombre !== null && $documento->nombre !== '') {
            $partes[] = Str::slug($documento->nombre, '-');
        }

        if ($documento->fecha_vencimiento !== null) {
            $partes[] = 'vence-'.$documento->fecha_vencimiento->format('Y-m-d');
        }

        $base = implode('_', $partes);
        $nombre = "{$base}.{$extension}";

        for ($copia = 2; in_array($nombre, $usados, true); $copia++) {
            $nombre = "{$base}_{$copia}.{$extension}";
        }

        return $nombre;
    }
}
