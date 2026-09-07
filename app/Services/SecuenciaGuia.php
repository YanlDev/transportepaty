<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reserva el siguiente correlativo de una serie de guías.
 *
 * SUNAT no tolera correlativos repetidos ni saltos, y calcularlos con un
 * `max() + 1` sobre las guías ya emitidas falla en cuanto dos personas emiten
 * a la vez: ambas leen el mismo máximo. Acá el número se reserva bloqueando la
 * fila de la serie dentro de una transacción, así que dos emisiones simultáneas
 * se ordenan en vez de chocar.
 */
class SecuenciaGuia
{
    public function siguiente(string $serie): int
    {
        return DB::transaction(function () use ($serie): int {
            $fila = DB::table('secuencias_gre')
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if ($fila === null) {
                DB::table('secuencias_gre')->insert([
                    'serie' => $serie,
                    'ultimo_correlativo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $siguiente = $fila->ultimo_correlativo + 1;

            DB::table('secuencias_gre')
                ->where('serie', $serie)
                ->update(['ultimo_correlativo' => $siguiente, 'updated_at' => now()]);

            return $siguiente;
        });
    }

    /**
     * El número tal como se muestra y se guarda: serie y correlativo de 8
     * dígitos, igual que lo imprime SUNAT.
     */
    public function formatear(string $serie, int $correlativo): string
    {
        return $serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);
    }
}
