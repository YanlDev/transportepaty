<?php

namespace App\Services;

use App\Enums\MotivoRetiroLlanta;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\DetalleSalida;
use App\Models\Llanta;
use App\Models\SalidaAlmacen;
use App\Models\Vehiculo;

/**
 * Las llantas que se pueden montar en una unidad sin saltarse el almacén.
 *
 * Una llanta nueva se monta contra una línea de un vale de salida a nombre de
 * la unidad: el vale dice que salió del almacén, y cada unidad de esa línea
 * se monta una sola vez. Una llanta que se retiró de otra unidad se vuelve a
 * montar sin vale: ya salió del almacén la primera vez, y sigue siendo la
 * misma llanta, con su historial.
 */
class LlantasDeAlmacen
{
    public const CLAVE_CATEGORIA = 'llantas';

    /**
     * Si el artículo es una llanta: está en la categoría de llantas.
     */
    public function esLlanta(Articulo $articulo): bool
    {
        return $articulo->categoria_articulo_id !== null
            && $articulo->categoria_articulo_id === CategoriaArticulo::query()->where('clave', self::CLAVE_CATEGORIA)->value('id');
    }

    /**
     * Cuántas llantas de esa línea de vale faltan montar: lo que salió, menos
     * lo que se devolvió, menos lo ya montado.
     */
    public function disponibles(DetalleSalida $detalle): int
    {
        return max(0, (int) floor((float) $detalle->cantidad - $detalle->cantidadDevuelta()) - $this->montadasDe($detalle));
    }

    /**
     * Cuántas llantas se montaron (y siguen registradas) con esa línea.
     */
    public function montadasDe(DetalleSalida $detalle): int
    {
        return Llanta::query()->where('detalle_salida_id', $detalle->id)->count();
    }

    /**
     * Las líneas de vale con llantas todavía por montar en la unidad.
     *
     * @return list<array{detalle_salida_id: int, vale: string, fecha: string, articulo_id: int, nombre: string, marca: string|null, disponibles: int}>
     */
    public function lineasPara(Vehiculo $vehiculo): array
    {
        $categoria = CategoriaArticulo::query()->where('clave', self::CLAVE_CATEGORIA)->value('id');

        if ($categoria === null) {
            return [];
        }

        return array_values(DetalleSalida::query()
            ->whereIn('salida_almacen_id', SalidaAlmacen::query()->vigentes()->where('vehiculo_id', $vehiculo->id)->select('id'))
            ->whereIn('articulo_id', Articulo::query()->where('categoria_articulo_id', $categoria)->select('id'))
            ->with(['salida', 'articulo'])
            ->orderBy('id')
            ->get()
            ->map(fn (DetalleSalida $detalle): array => [
                'detalle_salida_id' => $detalle->id,
                'vale' => $detalle->salida->numero(),
                'fecha' => $detalle->salida->fecha->toDateString(),
                'articulo_id' => $detalle->articulo_id,
                'nombre' => $detalle->articulo->nombre,
                'marca' => $detalle->articulo->marca,
                'disponibles' => $this->disponibles($detalle),
            ])
            ->filter(fn (array $linea): bool => $linea['disponibles'] > 0)
            ->all());
    }

    /**
     * Las llantas retiradas que se pueden volver a montar (en esta unidad o
     * en otra). Una retirada por daño no vuelve a rodar.
     *
     * @return list<array{id: int, descripcion: string, placa: string, retirada_el: string, motivo: string}>
     */
    public function retiradas(): array
    {
        return array_values(Llanta::query()
            ->whereNotNull('retirada_el')
            ->where('motivo_retiro', '!=', MotivoRetiroLlanta::Dano)
            ->with('vehiculo:id,placa')
            ->orderByDesc('retirada_el')
            ->limit(200)
            ->get()
            ->map(fn (Llanta $llanta): array => [
                'id' => $llanta->id,
                'descripcion' => trim(implode(' ', array_filter([$llanta->marca, $llanta->medida, $llanta->codigo]))) ?: "Llanta {$llanta->id}",
                'placa' => $llanta->vehiculo->placa,
                'retirada_el' => (string) $llanta->retirada_el?->toDateString(),
                'motivo' => (string) $llanta->motivo_retiro?->label(),
            ])
            ->all());
    }
}
