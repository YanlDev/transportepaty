<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->raiz = sys_get_temp_dir().'/respaldos-'.uniqid();
    config(['transpaty.respaldos.ruta' => $this->raiz, 'transpaty.respaldos.cantidad' => 3]);
    $this->travelTo('2026-10-15 02:00:00');
});

afterEach(function (): void {
    File::deleteDirectory($this->raiz);
});

/**
 * Lo que corre de verdad: el dump en formato custom, la lectura de su índice
 * y la copia de storage. La carpeta lleva la fecha de Lima, no la de UTC.
 */
it('dumps the base, checks it and copies storage into a folder named after the Lima date', function (): void {
    Process::fake();

    $this->artisan('transpaty:respaldar')->assertSuccessful();

    $carpeta = $this->raiz.'/20261014-2100';

    expect(File::isDirectory($carpeta))->toBeTrue()
        ->and(File::isDirectory($carpeta.'.parcial'))->toBeFalse();

    Process::assertRan(fn (PendingProcess $proceso): bool => $proceso->command[0] === 'pg_dump'
        && in_array('--format=custom', $proceso->command, true)
        && in_array('--file='.$carpeta.'.parcial/base.dump', $proceso->command, true)
        && array_key_exists('PGPASSWORD', $proceso->environment));
    Process::assertRan(fn (PendingProcess $proceso): bool => $proceso->command === ['pg_restore', '--list', $carpeta.'.parcial/base.dump']);
    Process::assertRan(fn (PendingProcess $proceso): bool => $proceso->command[0] === 'rsync'
        && end($proceso->command) === $carpeta.'.parcial/archivos/');
});

/**
 * Los archivos que no cambiaron se enlazan al respaldo anterior: cada día
 * ocupa solo lo nuevo.
 */
it('links the files to the previous respaldo', function (): void {
    File::ensureDirectoryExists($this->raiz.'/20261013-2100/archivos');
    Process::fake();

    $this->artisan('transpaty:respaldar')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $proceso): bool => $proceso->command[0] === 'rsync'
        && in_array('--link-dest='.$this->raiz.'/20261013-2100/archivos', $proceso->command, true));
});

it('keeps only the latest respaldos and clears the parciales', function (): void {
    foreach (['20261010-2100', '20261011-2100', '20261012-2100', '20261013-2100', '20261012-2100.parcial'] as $carpeta) {
        File::ensureDirectoryExists($this->raiz.'/'.$carpeta);
    }
    Process::fake();

    $this->artisan('transpaty:respaldar')->assertSuccessful();

    expect(collect(File::directories($this->raiz))->map(fn (string $carpeta): string => basename($carpeta))->sort()->values()->all())
        ->toBe(['20261012-2100', '20261013-2100', '20261014-2100']);
});

/**
 * Un dump que falla no deja una carpeta que parezca un respaldo bueno, ni
 * borra los anteriores.
 */
it('fails without leaving a broken respaldo or rotating the old ones', function (): void {
    foreach (['20261010-2100', '20261011-2100', '20261012-2100'] as $carpeta) {
        File::ensureDirectoryExists($this->raiz.'/'.$carpeta);
    }
    Process::fake([
        "'pg_dump'*" => Process::result(errorOutput: 'connection refused', exitCode: 1),
    ]);

    $this->artisan('transpaty:respaldar')
        ->expectsOutputToContain('connection refused')
        ->assertFailed();

    expect(collect(File::directories($this->raiz))->map(fn (string $carpeta): string => basename($carpeta))->sort()->values()->all())
        ->toBe(['20261010-2100', '20261011-2100', '20261012-2100']);
    Process::assertDidntRun(fn (PendingProcess $proceso, ProcessResult $resultado): bool => $proceso->command[0] === 'rsync');
});
