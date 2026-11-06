<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una dotación de la ruta según el peso de la carga: «hasta 8 t», «más de
 * 25 t». Se elige al programar si se sabe, o después con el peso de la GR.
 *
 * @property int $id
 * @property int $ruta_id
 * @property string $nombre
 * @property string|null $peso_desde Toneladas: más de esto.
 * @property string|null $peso_hasta Toneladas: hasta esto, inclusive.
 * @property int $orden
 * @property-read Ruta $ruta
 */
#[Fillable(['ruta_id', 'nombre', 'peso_desde', 'peso_hasta', 'orden'])]
class VarianteRuta extends Model
{
    use RegistraHistorial;

    protected $table = 'variantes_ruta';

    public function etiquetaHistorial(): ?string
    {
        return $this->nombre;
    }

    public function admitePeso(float $toneladas): bool
    {
        return ($this->peso_desde === null || $toneladas > (float) $this->peso_desde)
            && ($this->peso_hasta === null || $toneladas <= (float) $this->peso_hasta);
    }

    /**
     * @return BelongsTo<Ruta, $this>
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso_desde' => 'decimal:2',
            'peso_hasta' => 'decimal:2',
            'orden' => 'integer',
        ];
    }
}
