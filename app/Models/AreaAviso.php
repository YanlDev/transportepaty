<?php

namespace App\Models;

use Database\Factories\AreaAvisoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un área de la casa que recibe el aviso de cada salida programada
 * (abastecimiento, facturación, centro de control…). Se administran desde el
 * panel de WhatsApp: agregar un área es cargar su número, sin tocar código.
 *
 * Todas reciben lo mismo —unidad, conductor, cliente, lugar de carga y
 * destino—; `ve_flete` suma el precio acordado, que solo le corresponde a
 * quien factura.
 *
 * @property int $id
 * @property string $nombre
 * @property string $numero
 * @property bool $ve_flete
 * @property bool $activa
 * @property bool $recibe_recordatorio
 * @property int $orden
 */
#[Fillable(['nombre', 'numero', 've_flete', 'activa', 'recibe_recordatorio', 'orden'])]
class AreaAviso extends Model
{
    /** @use HasFactory<AreaAvisoFactory> */
    use HasFactory;

    protected $table = 'areas_aviso';

    /**
     * Las que están recibiendo avisos, en el orden en que se muestran.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true)->orderBy('orden')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            've_flete' => 'boolean',
            'activa' => 'boolean',
            'recibe_recordatorio' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
