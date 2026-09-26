<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes sueltos de la operación que se cambian desde la app y no desde el
 * .env del servidor —hoy, el teléfono de la oficina que va al pie de la
 * advertencia—. Una fila por clave.
 *
 * @property string $clave
 * @property string|null $valor
 */
#[Fillable(['clave', 'valor'])]
class Ajuste extends Model
{
    public const TELEFONO_OFICINA = 'telefono_oficina';

    /**
     * A qué hora (HH:MM, hora de Lima) se avisa de las unidades que siguen
     * sin GR. Vacío: no se manda.
     */
    public const HORA_RECORDATORIO = 'hora_recordatorio_sin_gr';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function valor(string $clave): ?string
    {
        $valor = self::query()->find($clave)?->valor;

        return filled($valor) ? $valor : null;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        self::query()->updateOrCreate(['clave' => $clave], ['valor' => filled($valor) ? trim($valor) : null]);
    }
}
