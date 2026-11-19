<?php

namespace App\Console\Commands;

use App\Services\ArticulosDelPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mantenimiento:crear-articulos')]
#[Description('Le crea su artículo de almacén (en stock cero) a cada ítem de los planes preventivos que no lo tenga')]
class CrearArticulosDelPlan extends Command
{
    public function handle(ArticulosDelPlan $articulos): int
    {
        $creados = $articulos->asegurar();

        $this->info($creados === 0
            ? 'Todos los ítems de los planes ya tienen su artículo.'
            : "Se crearon {$creados} artículos para los ítems de los planes preventivos.");

        return self::SUCCESS;
    }
}
