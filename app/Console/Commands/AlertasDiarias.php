<?php

namespace App\Console\Commands;

use App\Enums\TipoAlerta;
use App\Models\ConfiguracionAlerta;
use App\Services\Alertas;
use App\Services\RelojOperativo;
use App\Services\ResumenesDiarios;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Los resúmenes del día (documentos por vencer, facturas vencidas), cada uno
 * a la hora que tiene configurada.
 *
 * Corre cada minuto y decide solo: manda una vez por día cada resumen que
 * esté prendido, apenas pasó su hora. Si a esa hora el servidor estaba
 * caído, sale cuando vuelva. Un día sin nada que contar no manda nada.
 */
#[Signature('transpaty:alertas-diarias')]
#[Description('Manda los resúmenes diarios de alertas que ya pasaron su hora')]
class AlertasDiarias extends Command
{
    public function handle(Alertas $alertas, ResumenesDiarios $resumenes): int
    {
        $ahora = RelojOperativo::ahora();
        $inicioDelDia = $ahora->startOfDay();

        foreach (TipoAlerta::cases() as $tipo) {
            if (! $tipo->esResumenDiario()) {
                continue;
            }

            $configuracion = ConfiguracionAlerta::para($tipo);
            $hora = $configuracion->hora ?? $tipo->horaPorDefecto();

            if (! $configuracion->activa
                || $hora === null
                || $ahora->format('H:i') < $hora
                || ($configuracion->ultimo_envio_at !== null && $configuracion->ultimo_envio_at->gte($inicioDelDia))) {
                continue;
            }

            $resumen = $resumenes->de($tipo);

            if ($resumen !== null) {
                $alertas->avisar($tipo, $resumen['titulo'], $resumen['mensaje'], $resumen['url']);
                $this->info("{$tipo->label()}: {$resumen['titulo']}.");
            }

            // Se anota aunque no haya habido nada que contar: hoy ya se miró.
            $configuracion->ultimo_envio_at = now();
            $configuracion->saveQuietly();
        }

        return self::SUCCESS;
    }
}
