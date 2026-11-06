<?php

namespace App\Services;

use App\Models\Vuelta;

/**
 * La liquidación del ticket: lo autorizado contra lo real, y lo que falta
 * para poder cerrarlo. Cerrar es un paso de una persona, pero solo cuando el
 * ticket está completo: llegó a la base y cada combustible autorizado tiene
 * su despacho (el voucher, con precio). Lo demás (viáticos pendientes, un
 * ticket sin GR) no frena el cierre, pero se avisa.
 *
 * @phpstan-import-type GastosTicket from GastosDelTicket
 *
 * @phpstan-type Liquidacion array{
 *     pendientes: list<string>,
 *     avisos: list<string>,
 *     puede_cerrar: bool,
 *     combustible: array{autorizado: float, despachado: float, diferencia: float, importe: float, estimado: float},
 *     viaticos: array{depositado: float, pendiente: float},
 *     km: array{salida: int|null, llegada: int|null, recorridos: int|null},
 *     rendimiento: float|null,
 *     total: float,
 *     total_es_estimado: bool
 * }
 */
class LiquidacionTicket
{
    /**
     * El ticket tiene que venir con `paradas.viajes` y `revisiones`.
     *
     * @param  GastosTicket  $gastos
     * @return Liquidacion
     */
    public function de(Vuelta $vuelta, array $gastos): array
    {
        $pendientes = [];
        $avisos = [];

        if (! $vuelta->llegoABase()) {
            $pendientes[] = 'Registrar la llegada a la base.';
        }

        if ($gastos['despachos_pendientes'] > 0) {
            $pendientes[] = $gastos['despachos_pendientes'] === 1
                ? 'Registrar el despacho (voucher con precio) de 1 combustible autorizado.'
                : "Registrar el despacho (voucher con precio) de {$gastos['despachos_pendientes']} combustibles autorizados.";
        }

        if ($gastos['viatico_pendiente'] > 0) {
            $avisos[] = 'Hay viáticos de la ruta sin depositar por S/ '.number_format($gastos['viatico_pendiente'], 2).'.';
        }

        if ($vuelta->paradas->every(fn ($parada): bool => $parada->viajes->isEmpty())) {
            $avisos[] = 'El ticket no tiene ninguna GR asignada.';
        }

        $salida = $vuelta->revisiones->first(fn ($revision): bool => $revision->esApta())?->kilometraje;
        $recorridos = $salida !== null && $vuelta->km_llegada !== null && $vuelta->km_llegada >= $salida
            ? $vuelta->km_llegada - $salida
            : null;

        $despachado = (float) $gastos['total_despachado'];

        if ($recorridos === null && $vuelta->llegoABase()) {
            $avisos[] = 'Sin el km de salida (OK de taller) y el de llegada no se calcula el rendimiento.';
        }

        return [
            'pendientes' => $pendientes,
            'avisos' => $avisos,
            'puede_cerrar' => $pendientes === [],
            'combustible' => [
                'autorizado' => (float) $gastos['total_registrado'],
                'despachado' => $despachado,
                'diferencia' => round($despachado - (float) $gastos['total_registrado'], 2),
                'importe' => (float) $gastos['importe_registrado'],
                // Lo del voucher más lo autorizado que todavía no tiene voucher,
                // por su precio: al cerrar (todo con voucher) es el real.
                'estimado' => (float) $gastos['importe_estimado'],
            ],
            'viaticos' => [
                'depositado' => (float) $gastos['viatico_depositado'],
                'pendiente' => (float) $gastos['viatico_pendiente'],
            ],
            'km' => ['salida' => $salida, 'llegada' => $vuelta->km_llegada, 'recorridos' => $recorridos],
            'rendimiento' => $recorridos !== null && $despachado > 0 ? round($recorridos / $despachado, 2) : null,
            'total' => round((float) $gastos['importe_estimado'] + (float) $gastos['viatico_depositado'], 2),
            'total_es_estimado' => $gastos['despachos_pendientes'] > 0,
        ];
    }
}
