<?php

namespace App\Models;

use App\Enums\SituacionValeCompra;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cada vez que alguien abrió la página de verificación de un vale: cuándo,
 * desde dónde y qué se le mostró.
 *
 * @property int $id
 * @property int $vale_compra_id
 * @property string|null $ip
 * @property string|null $navegador
 * @property SituacionValeCompra $situacion
 * @property Carbon|null $created_at
 */
#[Fillable(['vale_compra_id', 'ip', 'navegador', 'situacion', 'created_at'])]
class ConsultaValeCompra extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'consultas_vale_compra';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'situacion' => SituacionValeCompra::class,
        ];
    }
}
