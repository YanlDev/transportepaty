<?php

namespace App\Models;

use App\Enums\ZonaRuta;
use App\Services\TablaDeTramos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que gasta la unidad de una zona a otra, cargada y vacía. Juliaca es la
 * base: un viaje particular es hasta el destino (cargado) y de ahí a
 * Juliaca (vacío, o cargado si trae algo). Null: por definir.
 *
 * @property int $id
 * @property ZonaRuta $zona_origen
 * @property ZonaRuta $zona_destino
 * @property string|null $galones_cargado
 * @property string|null $galones_vacio
 * @property string|null $nota
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GalonesPorTramo extends Model
{
    protected $table = 'galones_por_tramo';

    protected $fillable = ['zona_origen', 'zona_destino', 'galones_cargado', 'galones_vacio', 'nota'];

    protected static function booted(): void
    {
        // La tabla se lee una vez por pedido: al cambiarla se vuelve a leer.
        static::saved(fn () => app(TablaDeTramos::class)->olvidar());
        static::deleted(fn () => app(TablaDeTramos::class)->olvidar());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zona_origen' => ZonaRuta::class,
            'zona_destino' => ZonaRuta::class,
            'galones_cargado' => 'decimal:2',
            'galones_vacio' => 'decimal:2',
        ];
    }
}
