<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un conteo del equipamiento de una carreta en base. Lo que la carreta tiene
 * es lo de su último conteo.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property Carbon $fecha
 * @property string|null $observacion
 * @property int|null $user_id
 * @property-read Collection<int, DetalleConteoEquipamiento> $detalles
 * @property-read User|null $user
 */
#[Fillable(['vehiculo_id', 'fecha', 'observacion', 'user_id'])]
class ConteoEquipamiento extends Model
{
    use RegistraHistorial;

    protected $table = 'conteos_equipamiento';

    /**
     * @return HasMany<DetalleConteoEquipamiento, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleConteoEquipamiento::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }
}
