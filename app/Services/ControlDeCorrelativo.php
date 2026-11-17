<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\NumeroDeFacturaExplicado;

/**
 * Los números de factura que faltan en el correlativo: entre la primera
 * factura emitida desde el inicio de la cobranza y la última, los que no
 * están en el sistema (ni vigentes ni anulados) y nadie explicó todavía.
 *
 * Es un aviso, no un error: un número puede faltar porque la factura no se
 * subió, porque se anuló en SUNAT o porque no era un flete. Solo se dice
 * cuáles son, para que alguien lo mire.
 */
class ControlDeCorrelativo
{
    /**
     * @return list<array{serie: string, faltan: int, rangos: list<array{desde: int, hasta: int, cantidad: int, despues_del: string|null}>}>
     */
    public function faltantes(): array
    {
        $inicio = (string) config('transpaty.facturacion.inicio_cobranza');
        $series = [];

        Factura::query()
            ->conAnuladas()
            ->toBase()
            ->get(['numero', 'fecha_emision'])
            ->each(function (object $factura) use (&$series): void {
                if (preg_match('/^([A-Z0-9]+)-0*(\d+)$/', mb_strtoupper(trim((string) $factura->numero)), $partes) === 1) {
                    $series[$partes[1]][(int) $partes[2]] = substr((string) $factura->fecha_emision, 0, 10);
                }
            });

        $explicados = NumeroDeFacturaExplicado::query()->get(['serie', 'numero'])
            ->groupBy('serie')
            ->map(fn ($delaSerie): array => $delaSerie->pluck('numero')->map(fn ($numero): int => (int) $numero)->all());

        $resultado = [];

        foreach ($series as $serie => $numeros) {
            ksort($numeros);
            $desdeElInicio = array_keys(array_filter($numeros, fn (string $fecha): bool => $fecha >= $inicio));

            if ($desdeElInicio === []) {
                continue;
            }

            $primero = min($desdeElInicio);
            $ultimo = max(array_keys($numeros));
            $rangos = $this->rangos($primero, $ultimo, $numeros, array_flip($explicados->get($serie, [])));

            if ($rangos !== []) {
                $resultado[] = [
                    'serie' => (string) $serie,
                    'faltan' => array_sum(array_map(fn (array $rango): int => $rango['cantidad'], $rangos)),
                    'rangos' => $rangos,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Los tramos de números seguidos que faltan entre `$primero` y `$ultimo`.
     *
     * @param  array<int, string>  $presentes  número => fecha de emisión
     * @param  array<int, int>  $explicados  número => lo que sea
     * @return list<array{desde: int, hasta: int, cantidad: int, despues_del: string|null}>
     */
    private function rangos(int $primero, int $ultimo, array $presentes, array $explicados): array
    {
        $rangos = [];
        $desde = null;
        $ultimaFecha = null;
        $fechaDelTramo = null;

        for ($numero = $primero; $numero <= $ultimo + 1; $numero++) {
            $falta = $numero <= $ultimo && ! isset($presentes[$numero]) && ! isset($explicados[$numero]);

            if ($falta) {
                if ($desde === null) {
                    $desde = $numero;
                    $fechaDelTramo = $ultimaFecha;
                }

                continue;
            }

            if ($desde !== null) {
                $rangos[] = ['desde' => $desde, 'hasta' => $numero - 1, 'cantidad' => $numero - $desde, 'despues_del' => $fechaDelTramo];
                $desde = null;
            }

            $ultimaFecha = $presentes[$numero] ?? $ultimaFecha;
        }

        return $rangos;
    }
}
