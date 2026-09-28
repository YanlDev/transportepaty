<?php

namespace App\Console\Commands;

use App\Services\Sunat\ClienteGreSunat;
use App\Services\Sunat\SesionSol;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('transpaty:probar-sunat-gre
    {--guia= : GR-remitente a consultar, como RUC-SERIE-NUMERO (ej. 20100136741-T007-10088)}
    {--placa=* : Placas a consultar en el MTC}
    {--licencia= : Número de licencia a consultar en el MTC}
    {--dni= : DNI a consultar}
    {--nueva-sesion : Descarta el token en caché e inicia sesión de nuevo}')]
#[Description('Prueba la sesión SOL y las consultas de GRE contra SUNAT real. Solo lee: no emite nada.')]
class ProbarSunatGre extends Command
{
    public function handle(SesionSol $sesion, ClienteGreSunat $sunat): int
    {
        try {
            if ($this->option('nueva-sesion')) {
                $sesion->olvidar();
            }

            $sesion->token();
            $this->info('Sesión SOL OK: hay token de Emisión de GRE.');

            if ($guia = $this->option('guia')) {
                [$ruc, $serie, $numero] = array_pad(explode('-', (string) $guia, 3), 3, '');
                $resultado = $sunat->guiaRemitente($ruc, $serie, (int) $numero);
                $this->line("\n<info>GR-remitente {$guia}</info>");
                $this->line($resultado === null
                    ? 'No existe o SUNAT no la devolvió.'
                    : ($resultado['completa'] ? '[completa] ' : '[resumida: Paty no figura como transportista] ')
                        .json_encode($resultado['datos'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

            foreach ((array) $this->option('placa') as $placa) {
                $this->line("\n<info>Placa {$placa}</info> ".json_encode($sunat->placa($placa), JSON_UNESCAPED_UNICODE));
            }

            if ($licencia = $this->option('licencia')) {
                $this->line("\n<info>Licencia {$licencia}</info> ".json_encode($sunat->licencia((string) $licencia), JSON_UNESCAPED_UNICODE));
            }

            if ($dni = $this->option('dni')) {
                $this->line("\n<info>DNI {$dni}</info> ".json_encode($sunat->persona((string) $dni), JSON_UNESCAPED_UNICODE));
            }
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
