<?php

namespace App\Models;

use Database\Factories\SucursalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string|null $direccion
 * @property string|null $ciudad
 * @property string|null $telefono
 * @property bool $activa
 * @property int|null $vehiculos_count
 * @property int|null $conductores_count
 */
#[Fillable(['nombre', 'codigo', 'direccion', 'ciudad', 'telefono', 'activa'])]
class Sucursal extends Model
{
    /** @use HasFactory<SucursalFactory> */
    use HasFactory;

    protected $table = 'sucursales';

    /**
     * @return HasMany<Conductor, $this>
     */
    public function conductores(): HasMany
    {
        return $this->hasMany(Conductor::class);
    }

    /**
     * @return HasMany<Vehiculo, $this>
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }
}
