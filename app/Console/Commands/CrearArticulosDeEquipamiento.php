<?php

namespace App\Console\Commands;

use App\Services\ArticulosDeEquipamiento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('equipamiento:crear-articulos')]
#[Description('Le crea su artículo de almacén a cada ítem de equipamiento que no lo tenga')]
class CrearArticulosDeEquipamiento extends Command
{
    public function handle(ArticulosDeEquipamiento $articulos): int
    {
        $creados = $articulos->asegurar();

        $this->info($creados === 0
            ? 'Todos los ítems de equipamiento ya tienen su artículo.'
            : "Se ligaron {$creados} ítems de equipamiento a su artículo del almacén.");

        return self::SUCCESS;
    }
}
