<?php

namespace App\Console\Commands;

use App\Models\Viaje;
use App\Services\ImportadorViaje;
use App\Services\LectorGuiaRemision;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\Finder;

#[Signature('transpaty:importar-guias
    {ruta : Carpeta raíz con subcarpetas "GR ..." (ej. la carpeta OneDrive del cliente)}
    {--dry-run : Solo muestra qué se crearía/actualizaría, sin escribir nada}')]
#[Description('Importa como Viaje cada GR-transportista (PDF) encontrada en las subcarpetas "GR ..." de la ruta dada, saltando las que ya existen (clave numero_gr) para no reescribir su media sin necesidad.')]
class ImportarGuiasRemision extends Command
{
    public function __construct(
        private readonly ImportadorViaje $importador,
        private readonly LectorGuiaRemision $lector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ruta = rtrim((string) $this->argument('ruta'), '/');

        if (! is_dir($ruta)) {
            $this->error("La ruta no existe: {$ruta}");

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $creados = $actualizados = $noReconocidos = 0;

        $carpetasGr = Finder::create()->directories()->in($ruta)->depth(0)->name('/^GR /i')->sortByName();

        if (! $carpetasGr->hasResults()) {
            $this->warn("No se encontró ninguna carpeta \"GR ...\" dentro de {$ruta}.");

            return self::SUCCESS;
        }

        foreach ($carpetasGr as $carpeta) {
            $this->line("<info>{$carpeta->getFilename()}</info>");

            $archivos = Finder::create()->files()->in((string) $carpeta->getRealPath())->name('*.pdf')->sortByName();

            foreach ($archivos as $archivo) {
                $rutaArchivo = (string) $archivo->getRealPath();

                // Primero solo lee el PDF (sin escribir) para saber si el
                // numero_gr ya existe: así no se reescribe media de viajes que
                // no cambiaron solo por volver a pasar la carpeta completa.
                [$estadoPrevio, $numeroGr] = $this->previsualizar($rutaArchivo);

                if ($seco || $estadoPrevio !== 'creado') {
                    $estado = $estadoPrevio;
                } else {
                    [$estado, $numeroGr] = $this->importar($rutaArchivo, $archivo->getFilename());
                }

                match ($estado) {
                    'creado' => $creados++,
                    'actualizado' => $actualizados++,
                    'no_reconocido' => $noReconocidos++,
                };

                $marca = match ($estado) {
                    'creado' => '  + ',
                    'actualizado' => '  = ',
                    'no_reconocido' => '  ! ',
                };

                $detalle = $numeroGr ?? 'no se reconoció como GR de Paty';
                $this->line("{$marca}{$archivo->getFilename()} → {$detalle}");
            }
        }

        $this->newLine();
        $this->info(
            ($seco ? '[dry-run] ' : '')
            ."Faltantes creadas: {$creados} · ya existían: {$actualizados} · no reconocidas: {$noReconocidos}"
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0: 'creado'|'actualizado'|'no_reconocido', 1: string|null}
     */
    private function importar(string $rutaArchivo, string $nombreArchivo): array
    {
        $archivo = new UploadedFile($rutaArchivo, $nombreArchivo, 'application/pdf', null, true);
        $resultado = $this->importador->importar($archivo);

        if (! $resultado['reconocido'] || $resultado['viaje'] === null) {
            return ['no_reconocido', null];
        }

        $viaje = $resultado['viaje'];
        $estado = $viaje->wasRecentlyCreated ? 'creado' : 'actualizado';

        return [$estado, $viaje->numero_gr];
    }

    /**
     * Igual que `importar()`, pero sin escribir: solo lee el PDF y consulta si
     * el `numero_gr` ya existe, para el modo `--dry-run`.
     *
     * @return array{0: 'creado'|'actualizado'|'no_reconocido', 1: string|null}
     */
    private function previsualizar(string $rutaArchivo): array
    {
        try {
            $campos = $this->lector->extraerDesdeArchivo($rutaArchivo);
        } catch (\Throwable) {
            return ['no_reconocido', null];
        }

        $numeroGr = $campos['numero_gr'] ?? null;

        if ($numeroGr === null) {
            return ['no_reconocido', null];
        }

        $yaExiste = Viaje::query()->where('numero_gr', $numeroGr)->exists();

        return [$yaExiste ? 'actualizado' : 'creado', $numeroGr];
    }
}
