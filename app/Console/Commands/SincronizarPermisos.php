<?php

namespace App\Console\Commands;

use App\Services\SincronizadorPermisos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Corre en cada deploy: un permiso agregado a `App\Enums\Permiso` no existe en
 * la base hasta que esto lo crea, y sin él nadie —ni el admin— lo tendría.
 */
#[Signature('permisos:sincronizar')]
#[Description('Crea los permisos nuevos del catálogo, borra los obsoletos y le da todos al admin.')]
class SincronizarPermisos extends Command
{
    public function handle(SincronizadorPermisos $sincronizador): int
    {
        $sincronizador->sincronizar();

        $this->components->info('Permisos sincronizados.');

        return self::SUCCESS;
    }
}
