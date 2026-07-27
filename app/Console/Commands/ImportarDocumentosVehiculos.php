<?php

namespace App\Console\Commands;

use App\Enums\TipoDocumento;
use App\Models\Vehiculo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

#[Signature('transpaty:importar-documentos
    {ruta : Carpeta con subcarpetas nombradas por placa (ej. /mnt/d/Carpetas/tractos)}
    {--actualizar-fechas : Toma la fecha de vencimiento del nombre del archivo}
    {--dry-run : Muestra lo que haría sin escribir nada}')]
#[Description('Adjunta a cada vehículo los PDF de sus documentos, tomándolos de carpetas nombradas por placa.')]
class ImportarDocumentosVehiculos extends Command
{
    /**
     * Prefijo del nombre de archivo → tipo de documento.
     */
    private const TIPOS = [
        'soat' => TipoDocumento::Soat,
        'matpel' => TipoDocumento::Matpel,
        'rev_carga' => TipoDocumento::RevisionTecnicaCarga,
        'cert_habilitacion' => TipoDocumento::HabilitacionMtc,
        'tarjeta_propiedad' => TipoDocumento::TarjetaPropiedad,
    ];

    public function handle(): int
    {
        $ruta = rtrim((string) $this->argument('ruta'), '/');

        if (! is_dir($ruta)) {
            $this->error("La ruta no existe: {$ruta}");

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $adjuntados = $creados = $descartados = $omitidos = 0;

        foreach ($this->porPlacaYTipo($ruta) as $placa => $porTipo) {
            $vehiculo = Vehiculo::where('placa', $placa)->first();

            if ($vehiculo === null) {
                $this->warn("Sin vehículo para la placa {$placa}, se omite la carpeta.");
                $omitidos++;

                continue;
            }

            foreach ($porTipo as $candidatos) {
                // Un solo documento por tipo: gana el archivo con la fecha más
                // reciente en el nombre (el 9999 de "sin vencimiento" gana siempre).
                usort($candidatos, fn (array $a, array $b): int => strcmp($a['fecha'], $b['fecha']));
                $elegido = array_pop($candidatos);

                foreach ($candidatos as $descartado) {
                    $this->line("  ~ {$placa}: se descarta {$descartado['archivo']} (más antiguo)");
                    $descartados++;
                }

                $documento = $vehiculo->documentos()
                    ->where('tipo', $elegido['tipo'])
                    ->first();

                if ($seco) {
                    $adjuntados++;

                    continue;
                }

                if ($documento === null) {
                    $documento = $vehiculo->documentos()->create([
                        'tipo' => $elegido['tipo'],
                        'fecha_vencimiento' => $elegido['vence'],
                    ]);
                    $creados++;
                } elseif ($this->option('actualizar-fechas')) {
                    $documento->update(['fecha_vencimiento' => $elegido['vence']]);
                }

                $documento->addMedia($elegido['ruta'])
                    ->preservingOriginal()
                    ->toMediaCollection('archivo');

                $adjuntados++;
            }
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."Adjuntados: {$adjuntados} · creados: {$creados} · descartados por duplicado: {$descartados} · carpetas omitidas: {$omitidos}");

        return self::SUCCESS;
    }

    /**
     * Agrupa los PDF de la ruta por placa y por tipo de documento.
     *
     * @return array<string, array<string, list<array{tipo: TipoDocumento, fecha: string, vence: string|null, ruta: string, archivo: string}>>>
     */
    private function porPlacaYTipo(string $ruta): array
    {
        $agrupados = [];

        $archivos = Finder::create()->files()->in($ruta)->depth(1)->name('*.pdf')->sortByName();

        foreach ($archivos as $archivo) {
            $nombre = $archivo->getBasename('.pdf');

            if (! preg_match('/^(?<tipo>.+)_(?<fecha>\d{4}-\d{2}-\d{2})$/', $nombre, $partes)) {
                $this->warn("Nombre no reconocido, se omite: {$archivo->getFilename()}");

                continue;
            }

            $placa = basename(dirname((string) $archivo->getRealPath()));
            $tipo = self::TIPOS[$partes['tipo']] ?? TipoDocumento::Otro;

            $agrupados[$placa][$tipo->value][] = [
                'tipo' => $tipo,
                'fecha' => $partes['fecha'],
                // El año 9999 representa "sin vencimiento" (tarjeta de propiedad).
                'vence' => str_starts_with($partes['fecha'], '9999') ? null : $partes['fecha'],
                'ruta' => (string) $archivo->getRealPath(),
                'archivo' => $archivo->getFilename(),
            ];
        }

        return $agrupados;
    }
}
