<?php

namespace App\Services;

use App\Enums\NaturalezaCosto;
use App\Enums\TipoComponente;
use App\Models\ComponenteCosto;
use App\Models\ParametroFlota;
use Illuminate\Support\Collection;

/**
 * Convierte una ruta (kilómetros, días que la unidad queda tomada y los costos
 * propios del tramo) en una tarifa, línea por línea.
 *
 * El costo de un viaje tiene dos mitades que no se miden igual: la unidad
 * cuesta por día aunque esté parada esperando turno de carga, y cuesta por
 * kilómetro cuando rueda. Cotizar solo por distancia castiga las rutas cortas
 * con mucha espera y regala las largas y fluidas.
 *
 * El resultado separa lo directo de lo indirecto porque es lo que decide hasta
 * dónde se puede bajar en una negociación: un precio que no cubre los directos
 * pierde plata en cada vuelta.
 */
class CalculadoraCotizacion
{
    /**
     * Los conceptos propios del tramo. Son todos directos, y se cargan por
     * cotización porque cambian tanto de ruta en ruta que no sirven como tasa
     * global.
     *
     * @var array<string, string>
     */
    public const CONCEPTOS_RUTA = [
        'peajes' => 'Peajes',
        'viaticos' => 'Viáticos',
        'alojamiento' => 'Alojamiento',
        'cochera' => 'Cochera',
        'carga_descarga' => 'Carga y descarga',
        'otros_ruta' => 'Otros de ruta',
    ];

    /**
     * Las líneas de costo que salen de los componentes vigentes, ya resueltas
     * a su tasa. Se separa del cálculo para que una cotización ya emitida
     * pueda recalcularse con las líneas que tenía congeladas.
     *
     * @param  Collection<int, ComponenteCosto>  $componentes
     * @return list<array{nombre: string, tipo: string, naturaleza: string, tasa: float}>
     */
    public function lineasDesde(Collection $componentes, ParametroFlota $flota): array
    {
        return $componentes
            ->map(fn (ComponenteCosto $componente): array => [
                'nombre' => $componente->nombre,
                'tipo' => $componente->tipo->value,
                'naturaleza' => $componente->naturaleza->value,
                'tasa' => $componente->tasa($flota),
            ])
            ->values()
            ->all();
    }

    /**
     * Devuelve exactamente los importes que guarda una cotización. Cada paso se
     * redondea antes de alimentar al siguiente —y no al final— para que la
     * proforma cuadre con una calculadora en la mano: el cliente suma subtotal
     * más IGV y tiene que darle el total impreso, sin un centavo de diferencia.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{nombre: string, tipo: string, naturaleza: string, tasa: float}>  $lineas
     * @return array<string, mixed>
     */
    public function calcular(array $datos, array $lineas, float $igvPct): array
    {
        $km = (float) ($datos['km'] ?? 0);
        $dias = (float) ($datos['dias'] ?? 0);
        $margenPct = (float) ($datos['margen_pct'] ?? 0);

        $componentes = [];
        $totalDirecto = 0.0;
        $totalIndirecto = 0.0;

        foreach ($lineas as $linea) {
            $unidades = $linea['tipo'] === TipoComponente::FijoDia->value ? $dias : $km;
            $importe = round($linea['tasa'] * $unidades, 2);

            if ($linea['naturaleza'] === NaturalezaCosto::Directo->value) {
                $totalDirecto += $importe;
            } else {
                $totalIndirecto += $importe;
            }

            $componentes[] = [...$linea, 'importe' => $importe];
        }

        $ruta = [];

        foreach (self::CONCEPTOS_RUTA as $campo => $nombre) {
            $importe = round((float) ($datos[$campo] ?? 0), 2);
            $totalDirecto += $importe;

            $ruta[] = ['nombre' => $nombre, 'campo' => $campo, 'importe' => $importe];
        }

        $totalDirecto = round($totalDirecto, 2);
        $totalIndirecto = round($totalIndirecto, 2);
        $costoOperativo = round($totalDirecto + $totalIndirecto, 2);

        $margen = round($costoOperativo * $margenPct, 2);
        $subtotal = round($costoOperativo + $margen, 2);
        $igv = round($subtotal * $igvPct, 2);

        return [
            'desglose' => [
                'componentes' => $this->conParticipacion($componentes, $subtotal),
                'ruta' => $this->conParticipacion($ruta, $subtotal),
            ],
            'margen_pct' => $margenPct,
            'total_directo' => $totalDirecto,
            'total_indirecto' => $totalIndirecto,
            'costo_operativo' => $costoOperativo,
            'margen' => $margen,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => round($subtotal + $igv, 2),
        ];
    }

    /**
     * Cuánto pesa cada línea en el precio que ve el cliente. Se mide contra el
     * subtotal —costo más margen— y no contra el costo operativo, para que las
     * participaciones se lean sobre el mismo número que se cotiza.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function conParticipacion(array $lineas, float $subtotal): array
    {
        return array_map(fn (array $linea): array => [
            ...$linea,
            'participacion_pct' => $subtotal > 0.0
                ? round((float) $linea['importe'] / $subtotal, 6)
                : 0.0,
        ], $lineas);
    }
}
