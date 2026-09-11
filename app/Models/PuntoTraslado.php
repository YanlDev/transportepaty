<?php

namespace App\Models;

use Database\Factories\PuntoTrasladoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un lugar desde o hacia donde se traslada carga: una planta, una mina, un
 * terminal portuario.
 *
 * Existe por una sola razón: la GRE exige el ubigeo INEI del punto de partida
 * y del de llegada, y ese dato no se puede sacar del RUC del cliente. El RUC
 * da el domicilio fiscal, que casi nunca es donde está la mercadería —Minsur
 * tributa en San Borja y despacha desde Paracas hacia Antauta—. Como las rutas
 * se repiten, el catálogo se llena una vez y después solo se elige.
 *
 * @property int $id
 * @property string $nombre
 * @property string $ubigeo
 * @property string $direccion
 * @property string|null $ruc
 * @property string|null $cod_local
 * @property bool $activo
 */
#[Fillable([
    'nombre',
    'ubigeo',
    'direccion',
    'ruc',
    'cod_local',
    'activo',
])]
class PuntoTraslado extends Model
{
    /** @use HasFactory<PuntoTrasladoFactory> */
    use HasFactory;

    protected $table = 'puntos_traslado';

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Si el punto identifica además un establecimiento anexo del contribuyente.
     * Sin las dos piezas el dato no se puede declarar: SUNAT lee el código de
     * local en el contexto del RUC que lo registró.
     */
    public function tieneEstablecimiento(): bool
    {
        return $this->ruc !== null && $this->cod_local !== null;
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
