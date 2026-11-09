<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quién se le compra: repuestos, lubricantes, llantas, servicios.
 *
 * @property int $id
 * @property string|null $ruc
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $condicion_pago
 * @property string|null $notas
 * @property bool $activo
 */
#[Fillable([
    'ruc',
    'razon_social',
    'nombre_comercial',
    'contacto',
    'telefono',
    'email',
    'direccion',
    'condicion_pago',
    'notas',
    'activo',
])]
class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'proveedores';

    /**
     * @return HasMany<IngresoAlmacen, $this>
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(IngresoAlmacen::class);
    }

    /**
     * El nombre con que se lo reconoce: el comercial si tiene.
     */
    public function nombre(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBuscar(Builder $query, string $texto): void
    {
        $like = '%'.mb_strtolower($texto).'%';

        $query->where(function (Builder $query) use ($like): void {
            foreach (['razon_social', 'nombre_comercial', 'ruc', 'contacto'] as $campo) {
                $query->orWhereRaw("lower({$campo}) like ?", [$like]);
            }
        });
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->razon_social;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
