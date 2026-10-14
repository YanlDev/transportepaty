<?php

namespace App\Console\Commands;

use App\Services\RelojOperativo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * El respaldo diario: la base y los archivos de `storage/app` (PDF de GR,
 * facturas, documentos, fotos), en una carpeta por corrida con el nombre
 * `AAAAMMDD-HHMM` dentro de `transpaty.respaldos.ruta`.
 *
 * - `base.dump`: `pg_dump` en formato custom, que es el que restaura
 *   `pg_restore`. Se valida leyendo su índice: un dump cortado no pasa.
 * - `archivos/`: una copia completa de `storage/app`, pero los archivos que
 *   no cambiaron desde el respaldo anterior son enlaces duros al de ese día
 *   (`rsync --link-dest`). Cada carpeta se restaura sola y en disco solo
 *   ocupa lo nuevo.
 *
 * Mientras corre, la carpeta se llama `.parcial` y solo toma su nombre al
 * terminar bien: un respaldo a medias nunca pasa por uno bueno, ni sirve de
 * base para el siguiente. Se guardan los últimos `transpaty.respaldos.cantidad`.
 */
#[Signature('transpaty:respaldar')]
#[Description('Respalda la base y los archivos de storage en una carpeta nueva')]
class Respaldar extends Command
{
    public function handle(): int
    {
        $raiz = rtrim((string) config('transpaty.respaldos.ruta'), '/');
        File::ensureDirectoryExists($raiz);

        $anterior = $this->respaldos($raiz)->last();
        $destino = $raiz.'/'.RelojOperativo::ahora()->format('Ymd-Hi');
        $parcial = $destino.'.parcial';

        File::deleteDirectory($parcial);
        File::ensureDirectoryExists($parcial.'/archivos');

        try {
            $this->respaldarBase($parcial.'/base.dump');
            $this->respaldarArchivos($parcial.'/archivos', $anterior);
        } catch (ProcessFailedException $error) {
            File::deleteDirectory($parcial);

            $detalle = trim($error->result->errorOutput()) ?: 'código '.$error->result->exitCode();
            Log::error('El respaldo diario falló', ['detalle' => $detalle]);
            $this->error("El respaldo falló: {$detalle}");

            return self::FAILURE;
        }

        File::deleteDirectory($destino);
        File::moveDirectory($parcial, $destino);

        $this->rotar($raiz);

        $this->info("Respaldo listo en {$destino}.");

        return self::SUCCESS;
    }

    private function respaldarBase(string $archivo): void
    {
        // `pg_dump` es de Postgres: la conexión es esa aunque la de los tests
        // sea otra.
        $conexion = config('database.connections.pgsql');

        Process::env(['PGPASSWORD' => (string) $conexion['password']])
            ->timeout(600)
            ->run([
                'pg_dump',
                '--host='.$conexion['host'],
                '--port='.$conexion['port'],
                '--username='.$conexion['username'],
                '--format=custom',
                '--file='.$archivo,
                $conexion['database'],
            ])
            ->throw();

        // Leer el índice del dump: si se cortó a medias, esto falla.
        Process::run(['pg_restore', '--list', $archivo])->throw();
    }

    private function respaldarArchivos(string $destino, ?string $anterior): void
    {
        Process::timeout(1800)
            ->run([
                'rsync',
                '--archive',
                '--delete',
                ...($anterior === null ? [] : ['--link-dest='.$anterior.'/archivos']),
                storage_path('app').'/',
                $destino.'/',
            ])
            ->throw();
    }

    /**
     * Borra los respaldos que sobran, del más viejo al más nuevo, y lo que
     * haya quedado `.parcial` de una corrida que se cortó.
     */
    private function rotar(string $raiz): void
    {
        $respaldos = $this->respaldos($raiz);
        $sobran = $respaldos->count() - max(1, (int) config('transpaty.respaldos.cantidad'));

        $respaldos->take(max(0, $sobran))->each(fn (string $carpeta): bool => File::deleteDirectory($carpeta));

        collect(File::directories($raiz))
            ->filter(fn (string $carpeta): bool => str_ends_with($carpeta, '.parcial'))
            ->each(fn (string $carpeta): bool => File::deleteDirectory($carpeta));
    }

    /**
     * Los respaldos completos, del más viejo al más nuevo. El nombre
     * `AAAAMMDD-HHMM` ordena igual que la fecha.
     *
     * @return Collection<int, string>
     */
    private function respaldos(string $raiz): Collection
    {
        return collect(File::directories($raiz))
            ->filter(fn (string $carpeta): bool => preg_match('/^\d{8}-\d{4}$/', basename($carpeta)) === 1)
            ->sort()
            ->values();
    }
}
