<?php

namespace App\Services;

use App\Enums\TipoComponente;
use App\Models\ComponenteCosto;
use Illuminate\Support\Collection;

/**
 * Convierte una ruta (kilómetros y días que la unidad queda tomada) en una
 * tarifa, con la misma hoja con la que la casa cotiza: cada línea del
 * tarifario multiplicada por días o por kilómetros, el costo operativo, y el
 * margen encima.
 *
 * El costo de un viaje tiene dos mitades que no se miden igual: la unidad
 * cuesta por día aunque esté parada esperando turno de carga, y cuesta por
 * kilómetro cuando rueda. Cotizar solo por distancia castiga las rutas cortas
 * con mucha espera y regala las largas y fluidas.
 *
 * La hoja del cotizador (`resources/js/lib/tarifa.ts`) repite esta cuenta para
 * responder mientras se tipea; si una cambia, la otra también.
 */
class CalculadoraCotizacion
{
    /**
     * Las líneas vigentes del tarifario, listas para calcular. Se separa del
     * cálculo para que una cotización ya emitida pueda recalcularse con las
     * líneas que tenía congeladas.
     *
     * @param  Collection<int, ComponenteCosto>  $componentes
     * @return list<array{nombre: string, tipo: string, naturaleza: string, tasa: float}>
     */
    public function lineasDesde(Collection $componentes): array
    {
        return array_values(
            $componentes
                ->map(fn (ComponenteCosto $componente): array => [
                    'nombre' => $componente->nombre,
                    'tipo' => $componente->tipo->value,
                    'naturaleza' => $componente->naturaleza->value,
                    'tasa' => $componente->tasa,
                ])
                ->all()
        );
    }

    /**
     * Devuelve exactamente los importes que guarda una cotización. Cada paso se
     * redondea antes de alimentar al siguiente —y no al final— para que la
     * proforma cuadre con una calculadora en la mano: el cliente suma subtotal
     * más IGV y tiene que darle el total impreso, sin un centavo de diferencia.
     *
     * El margen es sobre el precio de venta, como en la hoja: con 12 % de
     * margen, el costo es el 88 % de la tarifa. Por eso la tarifa es el costo
     * dividido entre (1 − margen) y no el costo por (1 + margen).
     *
     * El retorno vacío se suma a la ida con las mismas tasas: la vuelta sin
     * carga no se cobra aparte, pero la unidad rueda y queda tomada igual.
     *
     * La tarifa del tarifario es una sugerencia (`tarifa_calculada`): si se
     * fija un precio unitario, lo que se cobra es cantidad × ese precio, y el
     * margen pasa a ser el real que deja ese precio. Sin precio fijado, el
     * unitario sale de repartir la tarifa calculada entre la cantidad.
     *
     * @param  array{km?: mixed, dias?: mixed, km_retorno?: mixed, dias_retorno?: mixed, margen_pct?: mixed, cantidad?: mixed, precio_unitario?: mixed}  $datos
     * @param  list<array{nombre: string, tipo: string, naturaleza: string, tasa: float}>  $lineas
     * @return array{desglose: array{componentes: list<array<string, mixed>>}, margen_pct: float, total_fijo: float, total_variable: float, costo_operativo: float, margen: float, tarifa_calculada: float, cantidad: float, precio_unitario: float, subtotal: float, igv: float, total: float}
     */
    public function calcular(array $datos, array $lineas, float $igvPct): array
    {
        $km = (float) ($datos['km'] ?? 0) + (float) ($datos['km_retorno'] ?? 0);
        $dias = (float) ($datos['dias'] ?? 0) + (float) ($datos['dias_retorno'] ?? 0);
        $margenPct = (float) ($datos['margen_pct'] ?? 0);
        $cantidadPedida = (float) ($datos['cantidad'] ?? 0);
        $cantidad = $cantidadPedida > 0 ? $cantidadPedida : 1.0;
        $precioFijado = (float) ($datos['precio_unitario'] ?? 0);

        $componentes = [];
        $totalFijo = 0.0;
        $totalVariable = 0.0;

        foreach ($lineas as $linea) {
            $esFijo = $linea['tipo'] === TipoComponente::FijoDia->value;
            $importe = round($linea['tasa'] * ($esFijo ? $dias : $km), 2);

            if ($esFijo) {
                $totalFijo += $importe;
            } else {
                $totalVariable += $importe;
            }

            $componentes[] = [...$linea, 'importe' => $importe];
        }

        $totalFijo = round($totalFijo, 2);
        $totalVariable = round($totalVariable, 2);
        $costoOperativo = round($totalFijo + $totalVariable, 2);

        $tarifaCalculada = $margenPct < 1.0 ? round($costoOperativo / (1 - $margenPct), 2) : $costoOperativo;

        // El subtotal es siempre cantidad × unitario, aunque el unitario salga
        // de repartir la tarifa: la proforma imprime los tres y tienen que
        // cuadrar con una calculadora en la mano.
        $precioUnitario = $precioFijado > 0 ? round($precioFijado, 2) : round($tarifaCalculada / $cantidad, 2);
        $subtotal = round($cantidad * $precioUnitario, 2);
        $margen = round($subtotal - $costoOperativo, 2);
        $igv = round($subtotal * $igvPct, 2);

        return [
            'desglose' => ['componentes' => $componentes],
            'margen_pct' => $margenPct,
            'total_fijo' => $totalFijo,
            'total_variable' => $totalVariable,
            'costo_operativo' => $costoOperativo,
            'margen' => $margen,
            'tarifa_calculada' => $tarifaCalculada,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => round($subtotal + $igv, 2),
        ];
    }
}
