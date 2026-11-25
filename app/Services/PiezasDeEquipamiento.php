<?php

namespace App\Services;

use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\PiezaEquipamiento;
use App\Models\Vehiculo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Los códigos de lo que se lleva pieza por pieza. Cuántas tiene la unidad lo
 * dice `EquipamientoDeUnidades`; acá se le pone código a cada una de las que
 * todavía no lo tienen.
 */
class PiezasDeEquipamiento
{
    /**
     * Les pone código a las piezas de la unidad que no lo tienen: tantas
     * como falten para llegar a lo asignado. Devuelve las nuevas.
     *
     * @return Collection<int, PiezaEquipamiento>
     */
    public function identificar(Vehiculo $vehiculo, ItemEquipamiento $item, int $asignado, ?ConteoEquipamiento $conteo = null, ?string $venceEl = null, ?int $userId = null): Collection
    {
        $conCodigo = PiezaEquipamiento::query()
            ->enUso()
            ->where('vehiculo_id', $vehiculo->id)
            ->where('item_equipamiento_id', $item->id)
            ->count();

        $nuevas = collect();

        for ($falta = $asignado - $conCodigo; $falta > 0; $falta--) {
            $nuevas->push(PiezaEquipamiento::create([
                'codigo' => $this->siguienteCodigo($item),
                'item_equipamiento_id' => $item->id,
                'vehiculo_id' => $vehiculo->id,
                'vence_el' => $venceEl,
                'conteo_equipamiento_id' => $conteo?->id,
                'user_id' => $userId,
            ]));
        }

        return $nuevas;
    }

    /**
     * Las letras con que empieza el código de las piezas de un ítem. Si no
     * las tiene, se le eligen de su nombre, sin repetir las de otro.
     */
    public function prefijoPara(ItemEquipamiento $item): string
    {
        if ($item->prefijo !== null) {
            return $item->prefijo;
        }

        $letras = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($item->nombre))) ?: 'PZ';
        $primera = $letras[0];
        $candidatos = collect(str_split(substr($letras, 1)))
            ->map(fn (string $letra): string => $primera.$letra)
            ->merge(collect(range(1, 99))->map(fn (int $numero): string => $primera.$numero))
            ->unique();

        $usados = ItemEquipamiento::query()->whereNotNull('prefijo')->pluck('prefijo')->all();
        $prefijo = (string) $candidatos->first(fn (string $candidato): bool => ! in_array($candidato, $usados, true));

        $item->update(['prefijo' => $prefijo]);

        return $prefijo;
    }

    private function siguienteCodigo(ItemEquipamiento $item): string
    {
        $prefijo = $this->prefijoPara($item);
        $siguiente = PiezaEquipamiento::query()->where('item_equipamiento_id', $item->id)->count() + 1;

        do {
            $codigo = sprintf('%s-%03d', $prefijo, $siguiente++);
        } while (PiezaEquipamiento::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
