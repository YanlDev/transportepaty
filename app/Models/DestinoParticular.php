<?php

namespace App\Models;

use App\Enums\ZonaRuta;
use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A dónde va la carga particular de vuelta (Arequipa, Tacna, Ilo…). Todos
 * menos los de la zona Juliaca son de paso: la unidad descarga y sigue a
 * Juliaca.
 *
 * @property int $id
 * @property string $lugar
 * @property ZonaRuta $zona
 * @property bool $activo
 * @property int $orden
 */
#[Fillable(['lugar', 'zona', 'activo', 'orden'])]
class DestinoParticular extends Model
{
    use RegistraHistorial;

    protected $table = 'destinos_particulares';

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('lugar');
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
