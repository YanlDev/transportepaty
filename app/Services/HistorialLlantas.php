<?php

namespace App\Services;

use App\Enums\TipoMovimientoLlanta;
use App\Models\Llanta;
use App\Models\MovimientoLlanta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corregir el historial de llantas sin dejar la unidad en un estado que no
 * existió. Lo que se anotó mal se corrige; eliminar, solo lo que se puede
 * deshacer limpio:
 *
 * - **Medición y reparación:** siempre; no mueven ninguna llanta.
 * - **Instalación:** si la llanta no tiene nada más anotado, y se va con
 *   ella: fue una llanta cargada por error.
 * - **Retiro:** si es lo último de la llanta y su posición sigue libre; la
 *   llanta vuelve a quedar puesta.
 * - **Rotación:** nunca: mueve dos llantas a la vez. Se corrige rotando de
 *   vuelta.
 */
class HistorialLlantas
{
    /**
     * Cuáles de estos movimientos se pueden eliminar, por id.
     *
     * @param  Collection<int, MovimientoLlanta>  $movimientos
     * @return array<int, bool>
     */
    public function eliminables(Collection $movimientos): array
    {
        $llantaIds = $movimientos->pluck('llanta_id')->unique();

        $todos = MovimientoLlanta::query()
            ->whereIn('llanta_id', $llantaIds)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'llanta_id']);

        $ultimos = $todos->unique('llanta_id')->pluck('id', 'llanta_id');
        $cantidades = $todos->countBy('llanta_id');

        $ocupadas = Llanta::query()
            ->montadas()
            ->whereIn('vehiculo_id', $movimientos->pluck('vehiculo_id')->unique())
            ->get(['vehiculo_id', 'posicion'])
            ->map(fn (Llanta $llanta): string => "{$llanta->vehiculo_id}-{$llanta->posicion}")
            ->all();

        return $movimientos
            ->mapWithKeys(fn (MovimientoLlanta $movimiento): array => [
                $movimiento->id => match ($movimiento->tipo) {
                    TipoMovimientoLlanta::Inspeccion, TipoMovimientoLlanta::Reparacion => true,
                    TipoMovimientoLlanta::Instalacion => $cantidades->get($movimiento->llanta_id) === 1,
                    TipoMovimientoLlanta::Retiro => $ultimos->get($movimiento->llanta_id) === $movimiento->id
                        && ! in_array("{$movimiento->vehiculo_id}-{$movimiento->posicion}", $ocupadas, true),
                    TipoMovimientoLlanta::Rotacion => false,
                },
            ])
            ->all();
    }

    /**
     * Lo que se anotó mal. La instalación y el retiro llevan su fecha y su
     * km también a la llanta, para que las dos digan lo mismo. La rotación no
     * lleva profundidad.
     */
    public function corregir(
        MovimientoLlanta $movimiento,
        string $fecha,
        ?int $kilometraje,
        ?float $profundidad,
        ?string $observacion,
    ): void {
        DB::transaction(function () use ($movimiento, $fecha, $kilometraje, $profundidad, $observacion): void {
            $movimiento->update([
                'fecha' => $fecha,
                'kilometraje' => $kilometraje,
                'observacion' => $observacion,
                'profundidad_mm' => $movimiento->tipo === TipoMovimientoLlanta::Rotacion ? null : $profundidad,
            ]);

            match ($movimiento->tipo) {
                TipoMovimientoLlanta::Instalacion => $movimiento->llanta->update([
                    'instalada_el' => $fecha,
                    'km_instalacion' => $kilometraje,
                ]),
                TipoMovimientoLlanta::Retiro => $movimiento->llanta->update([
                    'retirada_el' => $fecha,
                    'km_retiro' => $kilometraje,
                ]),
                default => null,
            };
        });
    }

    /**
     * @throws ValidationException si no se puede deshacer limpio
     */
    public function eliminar(MovimientoLlanta $movimiento): void
    {
        if (! ($this->eliminables(collect([$movimiento]))[$movimiento->id] ?? false)) {
            throw ValidationException::withMessages([
                'movimiento' => match ($movimiento->tipo) {
                    TipoMovimientoLlanta::Rotacion => 'Una rotación no se elimina: rota la llanta de vuelta.',
                    TipoMovimientoLlanta::Instalacion => 'La llanta ya tiene más movimientos: retírala en vez de eliminar su instalación.',
                    default => 'Este retiro ya no se puede deshacer: la posición tiene otra llanta o hubo movimientos después.',
                },
            ]);
        }

        DB::transaction(function () use ($movimiento): void {
            match ($movimiento->tipo) {
                // La llanta se va con su único movimiento, por la cascada.
                TipoMovimientoLlanta::Instalacion => $movimiento->llanta->delete(),
                TipoMovimientoLlanta::Retiro => $movimiento->llanta->update([
                    'retirada_el' => null,
                    'km_retiro' => null,
                    'motivo_retiro' => null,
                ]),
                default => null,
            };

            if ($movimiento->tipo !== TipoMovimientoLlanta::Instalacion) {
                $movimiento->delete();
            }
        });
    }
}
