<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\CategoriaGastoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * En qué se va la plata de un vale de gasto (peajes, movilidad, trámites…).
 * La lista la lleva gerencia. Una categoría que ya tiene vales no se borra:
 * se desactiva y deja de ofrecerse al pedir.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activa
 */
#[Fillable(['nombre', 'activa'])]
class CategoriaGasto extends Model
{
    /** @use HasFactory<CategoriaGastoFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'categorias_gasto';

    /**
     * @return HasMany<ValeGasto, $this>
     */
    public function vales(): HasMany
    {
        return $this->hasMany(ValeGasto::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true)->orderBy('nombre');
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->nombre;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
