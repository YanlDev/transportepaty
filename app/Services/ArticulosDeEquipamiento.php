<?php

namespace App\Services;

use App\Enums\UnidadMedida;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\ItemEquipamiento;

/**
 * Cada ítem de equipamiento tiene su artículo en el almacén, en la categoría
 * «Equipamiento»: así las fajas, los extintores y los botiquines se compran,
 * entran y salen como cualquier otro artículo, y lo que sale con un vale a
 * nombre de una unidad queda asignado a ella.
 */
class ArticulosDeEquipamiento
{
    public const CLAVE_CATEGORIA = 'equipamiento';

    /**
     * Le crea su artículo a cada ítem vigente que no lo tenga. Devuelve
     * cuántos creó. Se puede correr las veces que haga falta.
     */
    public function asegurar(): int
    {
        $creados = 0;

        foreach (ItemEquipamiento::query()->where('activo', true)->whereNull('articulo_id')->orderBy('orden')->get() as $item) {
            $item->update(['articulo_id' => $this->articuloPara($item)->id]);
            $creados++;
        }

        return $creados;
    }

    private function articuloPara(ItemEquipamiento $item): Articulo
    {
        $categoria = CategoriaArticulo::query()->firstOrCreate(
            ['clave' => self::CLAVE_CATEGORIA],
            ['nombre' => 'Equipamiento'],
        );
        $nombre = mb_strtoupper($item->nombre);

        // Si ya hay un artículo con ese nombre en la categoría, es ese.
        return Articulo::query()
            ->where('categoria_articulo_id', $categoria->id)
            ->whereRaw('upper(nombre) = ?', [$nombre])
            ->first()
            ?? Articulo::create([
                'codigo' => Articulo::siguienteCodigo(),
                'nombre' => $nombre,
                'categoria_articulo_id' => $categoria->id,
                'unidad' => UnidadMedida::Unidad,
                'factor_compra' => 1,
                'activo' => true,
            ]);
    }
}
