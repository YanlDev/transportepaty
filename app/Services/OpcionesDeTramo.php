<?php

namespace App\Services;

use App\Enums\SentidoTramo;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\DestinoParticular;
use App\Models\Ruta;
use App\Models\VarianteRuta;

/**
 * Las rutas del catálogo tal como las muestran los formularios: al programar
 * un ticket, al sumarle una ruta a un tramo y en la bandeja de GR.
 */
class OpcionesDeTramo
{
    /**
     * Las rutas activas del catálogo para cada tramo (principales y
     * complementos), con su dotación resumida y sus variantes, y los destinos
     * de la carga particular a otro destino.
     *
     * @return array{ida: list<array<string, mixed>>, regreso: list<array<string, mixed>>, destinos: list<array{lugar: string, zona: string}>}
     */
    public function todas(): array
    {
        $opciones = ['ida' => [], 'regreso' => []];
        $rutas = Ruta::query()->where('activa', true)->enOrden()->with(['lineas.punto:id,nombre', 'variantes'])->get();

        foreach ($rutas as $ruta) {
            foreach (SentidoTramo::cases() as $sentido) {
                if (! $ruta->sirvePara($sentido)) {
                    continue;
                }

                $opciones[$sentido->value][] = [
                    'value' => (string) $ruta->id,
                    'label' => $ruta->nombre,
                    'descripcion' => $ruta->descripcion ?? '',
                    'es_complemento' => $ruta->es_complemento,
                    'sube_con' => $ruta->sube_con?->value,
                    'pide_destino' => $ruta->pide_destino,
                    'paradas' => $ruta->paradas,
                    'tope' => $ruta->topePara(null),
                    'dotacion' => $this->resumenDeDotacion($ruta->lineasPara(null)),
                    'variantes' => $ruta->variantes->map(fn (VarianteRuta $variante): array => [
                        'id' => $variante->id,
                        'nombre' => $variante->nombre,
                        'tope' => $ruta->topePara($variante),
                    ])->values()->all(),
                ];
            }
        }

        return [
            ...$opciones,
            'destinos' => array_values(DestinoParticular::query()
                ->where('activo', true)
                ->enOrden()
                ->get()
                ->map(fn (DestinoParticular $destino): array => ['lugar' => $destino->lugar, 'zona' => $destino->zona->value])
                ->all()),
        ];
    }

    /**
     * La dotación en una línea: «Mina 100 gal · Arequipa 120 gal · viáticos
     * S/ 843».
     *
     * @param  list<array{tipo: string, punto: string|null, concepto: string, galones: float|null, tanque_lleno: bool, monto: float|null}>  $lineas
     */
    private function resumenDeDotacion(array $lineas): string
    {
        $combustible = array_map(
            fn (array $linea): string => ($linea['punto'] ?? $linea['concepto']).' '.match (true) {
                $linea['tanque_lleno'] => 'tanque full',
                $linea['galones'] === null => '¿? gal',
                default => rtrim(rtrim(number_format($linea['galones'], 1, '.', ''), '0'), '.').' gal',
            },
            array_values(array_filter($lineas, fn (array $linea): bool => $linea['tipo'] === TipoLineaAbastecimiento::Combustible->value)),
        );
        $viaticos = array_sum(array_column(array_filter($lineas, fn (array $linea): bool => $linea['tipo'] !== TipoLineaAbastecimiento::Combustible->value), 'monto'));

        return implode(' · ', array_filter([...$combustible, $viaticos > 0 ? 'viáticos S/ '.number_format($viaticos, 0) : null]));
    }
}
