<?php

namespace App\Services\Archivos;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recomprime un PDF con Ghostscript.
 *
 * Escribe siempre a un temporal en la misma carpeta y solo al final, si el
 * resultado es un PDF válido y pesa lo bastante menos, lo mueve encima del
 * original (`rename` dentro del mismo sistema de archivos es atómico: quien
 * lo esté descargando ve el viejo o el nuevo, nunca uno a medias). Ante
 * cualquier duda el original queda intacto.
 *
 * El comando va como lista de argumentos, sin pasar por un shell: ninguna
 * ruta se concatena en una línea de comandos.
 */
class CompresorPdf
{
    /**
     * @param  bool  $reemplazar  En `false` solo mide: comprime al temporal,
     *                            informa cuánto se ahorraría y lo descarta.
     * @param  int|null  $timeout  Segundos; por defecto el de la configuración.
     */
    public function comprimir(string $ruta, bool $reemplazar = true, ?int $timeout = null): ResultadoCompresion
    {
        $antes = (int) @filesize($ruta);

        if (! is_file($ruta) || $antes === 0) {
            return new ResultadoCompresion(ResultadoCompresion::FALLO, $antes, $antes, 'El archivo no existe o está vacío.');
        }

        if (! $this->hayGhostscript()) {
            return new ResultadoCompresion(ResultadoCompresion::SIN_GHOSTSCRIPT, $antes, $antes, 'Ghostscript no está instalado.');
        }

        $temporal = $ruta.'.'.Str::lower(Str::random(8)).'.tmp';

        try {
            $proceso = Process::timeout($timeout ?? (int) config('uploads.pdf.ghostscript.timeout'))->run([
                $this->binario(),
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.5',
                '-dPDFSETTINGS='.config('uploads.pdf.ghostscript.ajuste'),
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-dSAFER',
                '-sOutputFile='.$temporal,
                $ruta,
            ]);

            if ($proceso->failed()) {
                return new ResultadoCompresion(ResultadoCompresion::FALLO, $antes, $antes, trim($proceso->errorOutput()) ?: 'Ghostscript terminó con código '.$proceso->exitCode().'.');
            }

            if (! $this->esPdfValido($temporal)) {
                return new ResultadoCompresion(ResultadoCompresion::FALLO, $antes, $antes, 'Ghostscript no produjo un PDF válido.');
            }

            $despues = (int) filesize($temporal);

            if ($despues > $antes * (1 - (float) config('uploads.pdf.ahorro_minimo'))) {
                return new ResultadoCompresion(ResultadoCompresion::SIN_MEJORA, $antes, $despues);
            }

            if ($reemplazar) {
                $permisos = fileperms($ruta);

                if ($permisos !== false) {
                    @chmod($temporal, $permisos & 0777);
                }

                if (! rename($temporal, $ruta)) {
                    return new ResultadoCompresion(ResultadoCompresion::FALLO, $antes, $antes, 'No se pudo reemplazar el original.');
                }

                clearstatcache(true, $ruta);
            }

            return new ResultadoCompresion(ResultadoCompresion::COMPRIMIDO, $antes, $despues);
        } catch (Throwable $error) {
            return new ResultadoCompresion(ResultadoCompresion::FALLO, $antes, $antes, $error->getMessage());
        } finally {
            if (is_file($temporal)) {
                @unlink($temporal);
            }
        }
    }

    public function hayGhostscript(): bool
    {
        try {
            return Process::timeout(10)->run([$this->binario(), '--version'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function binario(): string
    {
        return (string) config('uploads.pdf.ghostscript.binario');
    }

    /**
     * Empieza como PDF y trae su marca de fin: un archivo cortado a la mitad
     * no pasa.
     */
    private function esPdfValido(string $ruta): bool
    {
        $peso = is_file($ruta) ? (int) filesize($ruta) : 0;

        if ($peso < 100) {
            return false;
        }

        $archivo = fopen($ruta, 'rb');

        if ($archivo === false) {
            return false;
        }

        $cabecera = (string) fread($archivo, 5);
        fseek($archivo, -min(1024, $peso), SEEK_END);
        $cola = (string) fread($archivo, 1024);
        fclose($archivo);

        return $cabecera === '%PDF-' && str_contains($cola, '%%EOF');
    }
}
