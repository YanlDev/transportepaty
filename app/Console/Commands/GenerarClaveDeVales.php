<?php

namespace App\Console\Commands;

use App\Services\FirmaDeVales;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vales:generar-clave')]
#[Description('Genera la clave con que el servidor firma los vales de compra')]
class GenerarClaveDeVales extends Command
{
    /**
     * Solo la muestra: ponerla en el `.env` es una decisión de quien
     * despliega. Cambiarla deja sin validar los vales ya firmados.
     */
    public function handle(FirmaDeVales $firma): int
    {
        if ($firma->tieneClave()) {
            $this->warn('Este servidor ya tiene una clave. Si la cambias, los vales ya firmados dejan de validar.');
        }

        $claves = $firma->generarClaves();

        $this->line('Agrega esta línea al .env (y no la compartas):');
        $this->newLine();
        $this->line("TRANSPATY_VALES_CLAVE_FIRMA={$claves['privada']}");
        $this->newLine();
        $this->line("Clave pública: {$claves['publica']}");

        return self::SUCCESS;
    }
}
