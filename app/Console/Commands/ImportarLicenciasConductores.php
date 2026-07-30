<?php

namespace App\Console\Commands;

use App\Enums\TipoDocumentoConductor;
use App\Models\Conductor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

#[Signature('transpaty:importar-licencias
    {ruta : Carpeta con los PDF nombrados "APELLIDOS NOMBRES.pdf"}
    {--tipo=licencia_conducir : Tipo de documento a registrar}
    {--dry-run : Muestra a quién se asignaría cada archivo sin escribir nada}')]
#[Description('Adjunta a cada conductor su licencia escaneada, emparejando por nombre.')]
class ImportarLicenciasConductores extends Command
{
    /**
     * Similitud mínima para aceptar un emparejamiento aproximado. Los nombres de
     * los archivos traen erratas ("GUITIERREZ" por "GUTIERREZ"), así que el
     * match exacto deja fuera a demasiados.
     */
    private const SIMILITUD_MINIMA = 85.0;

    public function handle(): int
    {
        $ruta = rtrim((string) $this->argument('ruta'), '/');

        if (! is_dir($ruta)) {
            $this->error("La ruta no existe: {$ruta}");

            return self::FAILURE;
        }

        $tipo = TipoDocumentoConductor::tryFrom((string) $this->option('tipo'));

        if ($tipo === null) {
            $this->error('Tipo de documento inválido.');

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $conductores = Conductor::all();
        $exactos = $aproximados = $sinMatch = 0;

        foreach (Finder::create()->files()->in($ruta)->depth(0)->name('*.pdf')->sortByName() as $archivo) {
            $nombreArchivo = $archivo->getBasename('.pdf');
            [$conductor, $similitud] = $this->emparejar($nombreArchivo, $conductores);

            if ($conductor === null) {
                $this->warn("  sin conductor: {$nombreArchivo}");
                $sinMatch++;

                continue;
            }

            if ($similitud < 100.0) {
                $this->line(sprintf(
                    '  ~ %s → %s (%.0f%%)',
                    $nombreArchivo,
                    $conductor->nombre_completo,
                    $similitud,
                ));
                $aproximados++;
            } else {
                $exactos++;
            }

            if ($seco) {
                continue;
            }

            $documento = $conductor->documentos()->updateOrCreate(
                ['tipo' => $tipo],
                ['numero' => $tipo === TipoDocumentoConductor::LicenciaConducir ? $conductor->licencia : null],
            );

            $documento->addMedia((string) $archivo->getRealPath())
                ->preservingOriginal()
                ->toMediaCollection('archivo');
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."{$tipo->label()} → exactos: {$exactos} · aproximados: {$aproximados} · sin conductor: {$sinMatch}");

        return self::SUCCESS;
    }

    /**
     * Busca al conductor cuyo nombre completo más se parece al del archivo.
     * Devuelve también la similitud para poder distinguir el match exacto del
     * aproximado y dejar constancia de este último en la salida.
     *
     * @param  Collection<int, Conductor>  $conductores
     * @return array{0: Conductor|null, 1: float}
     */
    private function emparejar(string $nombreArchivo, Collection $conductores): array
    {
        $buscado = $this->normalizar($nombreArchivo);
        $mejor = null;
        $mejorSimilitud = 0.0;

        foreach ($conductores as $conductor) {
            $candidato = $this->normalizar($conductor->apellidos.' '.$conductor->nombres);

            if ($candidato === $buscado) {
                return [$conductor, 100.0];
            }

            similar_text($buscado, $candidato, $porcentaje);

            if ($porcentaje > $mejorSimilitud) {
                $mejorSimilitud = $porcentaje;
                $mejor = $conductor;
            }
        }

        return $mejorSimilitud >= self::SIMILITUD_MINIMA
            ? [$mejor, $mejorSimilitud]
            : [null, 0.0];
    }

    /**
     * Mayúsculas sin tildes ni espacios de más, para que "AVENDAÑO" y
     * "AVENDANO" cuenten como el mismo nombre.
     */
    private function normalizar(string $valor): string
    {
        $sinTildes = strtr(mb_strtoupper(trim($valor)), [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);

        return (string) preg_replace('/\s+/', ' ', str_replace('.', ' ', $sinTildes));
    }
}
