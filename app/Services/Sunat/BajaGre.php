<?php

namespace App\Services\Sunat;

use App\Enums\MotivoBajaGre;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Da de baja en SUNAT la GR-transportista de un viaje y la deja anulada en
 * Transpaty, en ese orden: si SUNAT no la da de baja, en Transpaty no cambia
 * nada.
 */
class BajaGre
{
    public function __construct(private readonly ClienteGreSunat $sunat) {}

    /**
     * @throws BajaRechazada SUNAT no la dio de baja
     * @throws RuntimeException no se pudo saber si quedó de baja
     */
    public function darDeBaja(Viaje $viaje, MotivoBajaGre $motivo, User $usuario, ?string $observacion = null): void
    {
        if (! preg_match('/^([A-Z0-9]{4})-0*(\d+)$/', Str::upper(trim($viaje->numero_gr)), $partes)) {
            throw new BajaRechazada("«{$viaje->numero_gr}» no es un número de GR que se pueda dar de baja.");
        }

        [, $serie, $numero] = $partes;
        $ruc = (string) config('services.sunat_sol.ruc');

        // Doble clic: el segundo espera y encuentra la GR ya dada de baja.
        Cache::lock("sunat.baja.{$viaje->id}", 60)->block(45, function () use ($viaje, $motivo, $usuario, $observacion, $ruc, $serie, $numero): void {
            if ($viaje->refresh()->baja_sunat_at !== null) {
                return;
            }

            Log::channel('sunat')->info("SUNAT GRE: dando de baja {$viaje->numero_gr}.", ['motivo' => $motivo->value, 'usuario' => $usuario->id]);

            try {
                $registrada = $this->sunat->darDeBaja($ruc, $serie, (int) $numero, $motivo);
            } catch (BajaRechazada $rechazo) {
                Log::channel('sunat')->warning("SUNAT GRE: no se dio de baja {$viaje->numero_gr}.", ['motivo' => $rechazo->getMessage()]);

                throw $rechazo;
            } catch (RuntimeException $duda) {
                Log::channel('sunat')->error("SUNAT GRE: baja EN DUDA de {$viaje->numero_gr}, revisar en SOL.", ['motivo' => $duda->getMessage()]);

                throw $duda;
            }

            Log::channel('sunat')->info("SUNAT GRE: {$viaje->numero_gr} dada de baja.", ['registrada' => $registrada->toIso8601String()]);

            $viaje->forceFill([
                'baja_sunat_at' => $registrada,
                'motivo_baja_sunat' => $motivo->value,
                'anulada_at' => $viaje->anulada_at ?? now(),
                'anulada_por' => $viaje->anulada_por ?? $usuario->id,
                'motivo_anulacion' => $observacion ?: $viaje->motivo_anulacion ?: "Dada de baja en SUNAT: {$motivo->label()}",
            ])->save();
        });
    }
}
