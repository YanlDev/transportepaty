<?php

namespace App\Services;

use App\Enums\EstadoEquipamiento;
use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\Vehiculo;
use Illuminate\Support\Collection;

/**
 * Qué lleva cada carreta según su último conteo, frente al mínimo de cada
 * ítem. Nada se guarda: contar de nuevo lo actualiza.
 *
 * - **Faltan** de un ítem: mínimo − buenas, si es positivo. Las dañadas no
 *   cuentan: una faja rota no sujeta la carga.
 * - **Le falta:** algún ítem con mínimo tiene menos buenas.
 * - **Sin contar:** nunca se contó, y no hay de dónde decir qué lleva.
 *
 * @phpstan-type ItemCarreta array{id: int, nombre: string, minimo: int|null, buenas: int|null, danadas: int|null, faltan: int}
 * @phpstan-type EstadoCarreta array{
 *     conteo: array{id: int, fecha: string}|null,
 *     items: list<ItemCarreta>,
 *     faltan: int,
 *     estado: EstadoEquipamiento,
 * }
 */
class EstadoEquipamientoCarretas
{
    /**
     * @return EstadoCarreta
     */
    public function paraVehiculo(Vehiculo $vehiculo): array
    {
        return $this->paraFlota(collect([$vehiculo]))[$vehiculo->id];
    }

    /**
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @return array<int, EstadoCarreta>
     */
    public function paraFlota(Collection $vehiculos): array
    {
        $items = ItemEquipamiento::query()->vigentes()->get();

        $ultimos = ConteoEquipamiento::query()
            ->whereIn('vehiculo_id', $vehiculos->pluck('id'))
            ->with('detalles')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->unique('vehiculo_id')
            ->keyBy('vehiculo_id');

        $conMinimo = $items->whereNotNull('minimo')->isNotEmpty();
        $resultado = [];

        foreach ($vehiculos as $vehiculo) {
            $conteo = $ultimos->get($vehiculo->id);
            $detalles = $conteo === null ? [] : $conteo->detalles->keyBy('item_equipamiento_id')->all();

            $filas = array_values($items->map(function (ItemEquipamiento $item) use ($conteo, $detalles): array {
                $detalle = $detalles[$item->id] ?? null;
                // Un ítem que no estaba en el conteo se tomó como cero.
                $buenas = $conteo === null ? null : ($detalle === null ? 0 : $detalle->buenas);

                return [
                    'id' => $item->id,
                    'nombre' => $item->nombre,
                    'minimo' => $item->minimo,
                    'buenas' => $buenas,
                    'danadas' => $conteo === null ? null : ($detalle === null ? 0 : $detalle->danadas),
                    'faltan' => $item->minimo === null || $buenas === null ? 0 : max(0, $item->minimo - $buenas),
                ];
            })->all());

            $faltan = 0;

            foreach ($filas as $fila) {
                $faltan += $fila['faltan'];
            }

            $resultado[$vehiculo->id] = [
                'conteo' => $conteo === null ? null : ['id' => $conteo->id, 'fecha' => $conteo->fecha->toDateString()],
                'items' => $filas,
                'faltan' => $faltan,
                'estado' => match (true) {
                    $conteo === null => EstadoEquipamiento::SinContar,
                    $faltan > 0 => EstadoEquipamiento::Falta,
                    $conMinimo => EstadoEquipamiento::Completo,
                    default => EstadoEquipamiento::Contado,
                },
            ];
        }

        return $resultado;
    }
}
