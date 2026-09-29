<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Viaje;
use App\Services\ImportadorViaje;
use App\Services\LectorGuiaRemision;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('transpaty:completar-remitentes
    {--dry-run : Solo muestra qué cambiaría}
    {--con-cliente : Además corrige el cliente según la regla del importador (el subcontratador; si no hay, el remitente)}')]
#[Description('Vuelve a leer el PDF de cada viaje para guardar el remitente aparte del cliente, y opcionalmente corregir el cliente.')]
class CompletarRemitentes extends Command
{
    public function handle(LectorGuiaRemision $lector, ImportadorViaje $importador): int
    {
        $seco = (bool) $this->option('dry-run');
        $conCliente = (bool) $this->option('con-cliente');
        $remitentes = 0;
        $clientes = [];
        $sinPdf = 0;

        Viaje::query()->conAnuladas()->with('media')->orderBy('id')->each(function (Viaje $viaje) use ($lector, $importador, $seco, $conCliente, &$remitentes, &$clientes, &$sinPdf): void {
            $ruta = $viaje->getFirstMediaPath('archivo');

            if ($ruta === '' || ! is_file($ruta)) {
                $sinPdf++;

                return;
            }

            try {
                $campos = $lector->extraerDesdeArchivo($ruta);
            } catch (Throwable) {
                $sinPdf++;

                return;
            }

            if (($campos['cliente'] ?? null) === null) {
                return;
            }

            $cambios = [
                'remitente' => ImportadorViaje::normalizarRazonSocial($campos['cliente']),
                'remitente_ruc' => $campos['cliente_ruc'],
            ];

            if ($viaje->remitente !== $cambios['remitente'] || $viaje->remitente_ruc !== $cambios['remitente_ruc']) {
                $remitentes++;
            }

            [$cliente, $clienteRuc] = $importador->clienteReal($campos);

            // Se compara por RUC: el nombre puede venir escrito distinto.
            if ($clienteRuc !== null && $clienteRuc !== $viaje->cliente_ruc) {
                $clientes[] = "{$viaje->numero_gr}: {$viaje->cliente} → {$cliente}";

                if ($conCliente) {
                    $cambios += [
                        'cliente' => $cliente,
                        'cliente_ruc' => $clienteRuc,
                        'cliente_id' => Cliente::porRuc($clienteRuc)?->id,
                    ];
                }
            }

            if (! $seco) {
                $viaje->forceFill($cambios)->saveQuietly();
            }
        });

        $this->info(($seco ? '[dry-run] ' : '')."Remitentes completados o corregidos: {$remitentes} · viajes sin PDF legible: {$sinPdf}");

        if ($clientes !== []) {
            $this->line('');
            $this->warn(count($clientes).' viajes tendrían otro cliente con la regla nueva'.($conCliente ? ($seco ? '' : ' (corregidos)') : ' (no se tocan sin --con-cliente)').':');

            foreach ($clientes as $linea) {
                $this->line("  {$linea}");
            }
        }

        return self::SUCCESS;
    }
}
