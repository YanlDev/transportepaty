<?php

namespace App\Models;

use Database\Factories\ConductorFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sucursal_id
 * @property int|null $user_id
 * @property string $nombres
 * @property string $apellidos
 * @property string $documento
 * @property string|null $licencia
 * @property string|null $categoria_licencia
 * @property Carbon|null $licencia_vence
 * @property string|null $telefono
 * @property string|null $email
 * @property bool $activo
 * @property-read string $nombre_completo
 */
#[Fillable([
    'sucursal_id',
    'user_id',
    'nombres',
    'apellidos',
    'documento',
    'licencia',
    'categoria_licencia',
    'licencia_vence',
    'telefono',
    'email',
    'activo',
])]
#[Appends(['nombre_completo'])]
class Conductor extends Model
{
    /** @use HasFactory<ConductorFactory> */
    use HasFactory;

    protected $table = 'conductores';

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Vehiculo, $this>
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->nombres} {$this->apellidos}"));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'licencia_vence' => 'date:Y-m-d',
            'activo' => 'boolean',
        ];
    }
}
