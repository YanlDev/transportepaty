<?php

namespace App\Models;

use App\Enums\ZonaRuta;
use Database\Factories\PuntoAbastecimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Dónde se carga combustible: Kio a la entrada de Lima, la mina, Arequipa,
 * Nazca… La zona es opcional: hay puntos de paso que no son una zona del
 * circuito.
 *
 * @property int $id
 * @property string $nombre
 * @property ZonaRuta|null $zona
 * @property string|null $detalle
 * @property bool $activo
 * @property int $orden
 */
#[Fillable(['nombre', 'zona', 'detalle', 'activo', 'orden'])]
class PuntoAbastecimiento extends Model
{
    /** @use HasFactory<PuntoAbastecimientoFactory> */
    use HasFactory;

    protected $table = 'puntos_abastecimiento';

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('nombre');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zona' => ZonaRuta::class,
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
