<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un movimiento del sistema: el alta, el cambio, la anulación o la baja de
 * un registro, con quién lo hizo y qué valores cambiaron. Lo escribe el trait
 * `RegistraHistorial`; nunca se edita.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $accion creado, editado, anulado o eliminado
 * @property string $modelo El nombre corto de la clase: «Viatico», «Vuelta».
 * @property int $modelo_id
 * @property string|null $etiqueta
 * @property array<string, array{0: mixed, 1: mixed}>|null $cambios Campo → [antes, después].
 * @property Carbon $created_at
 * @property-read User|null $usuario
 */
#[Fillable(['user_id', 'accion', 'modelo', 'modelo_id', 'etiqueta', 'cambios'])]
class HistorialCambio extends Model
{
    protected $table = 'historial_cambios';

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
