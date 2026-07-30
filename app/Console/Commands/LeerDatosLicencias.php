<?php

namespace App\Console\Commands;

use App\Models\ConductorDocumento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('transpaty:leer-licencias
    {--sobrescribir : Reemplaza también las fechas ya cargadas}
    {--dry-run : Muestra lo que leería sin guardar nada}')]
#[Description('Lee número y fechas de las licencias electrónicas del MTC que traen capa de texto.')]
class LeerDatosLicencias extends Command
{
    /**
     * Caracteres mínimos para dar por buena la capa de texto. Las licencias
     * escaneadas devuelven casi nada y hay que descartarlas: leerlas exigiría
     * OCR, que en estas tarjetas confunde dígitos y no es fiable para una fecha
     * de vencimiento.
     */
    private const MINIMO_TEXTO = 80;

    public function handle(): int
    {
        if (! $this->binarioDisponible()) {
            $this->error('Falta pdftotext. Instálalo con: sudo apt install poppler-utils');

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $leidos = $escaneados = 0;

        foreach (ConductorDocumento::with(['media', 'conductor'])->get() as $documento) {
            $media = $documento->getFirstMedia('archivo');

            if ($media === null || ! file_exists($media->getPath())) {
                continue;
            }

            $texto = $this->extraerTexto($media->getPath());

            if (mb_strlen((string) preg_replace('/\s+/', '', $texto)) < self::MINIMO_TEXTO) {
                $escaneados++;

                continue;
            }

            $datos = array_filter([
                'numero' => $this->buscarNumero($texto),
                'fecha_emision' => $this->buscarFecha($texto, 'Expedici[oó]n'),
                'fecha_vencimiento' => $this->buscarFecha($texto, 'Revalidaci[oó]n'),
            ]);

            if ($datos === []) {
                continue;
            }

            if (! $this->option('sobrescribir')) {
                $datos = array_filter(
                    $datos,
                    fn (string $campo): bool => $documento->{$campo} === null,
                    ARRAY_FILTER_USE_KEY,
                );
            }

            if ($datos === []) {
                continue;
            }

            $this->line(sprintf(
                '  %s → %s',
                $documento->conductor->nombre_completo,
                collect($datos)->map(fn ($v, $k): string => "{$k}={$v}")->implode(' · '),
            ));

            if (! $seco) {
                $documento->update($datos);
            }

            $leidos++;
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."Actualizados: {$leidos} · escaneados sin capa de texto: {$escaneados}");

        return self::SUCCESS;
    }

    private function binarioDisponible(): bool
    {
        return trim((string) shell_exec('command -v pdftotext')) !== '';
    }

    private function extraerTexto(string $ruta): string
    {
        return (string) shell_exec('pdftotext -layout '.escapeshellarg($ruta).' - 2>/dev/null');
    }

    /**
     * El número de licencia peruano es una letra seguida de ocho dígitos.
     */
    private function buscarNumero(string $texto): ?string
    {
        return preg_match('/\b([A-Z]\d{8})\b/', $texto, $c) === 1 ? $c[1] : null;
    }

    /**
     * La fecha va en la línea siguiente a su etiqueta y viene en formato d/m/Y.
     */
    private function buscarFecha(string $texto, string $etiqueta): ?string
    {
        if (preg_match('/'.$etiqueta.'\s*\R\s*(\d{2}\/\d{2}\/\d{4})/iu', $texto, $c) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', $c[1])?->toDateString();
    }
}
