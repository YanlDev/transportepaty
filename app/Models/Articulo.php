<?php

namespace App\Models;

use App\Enums\UnidadMedida;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\ArticuloFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un artículo del almacén: un filtro, un aceite, una pastilla de freno.
 *
 * `stock`, `costo_promedio` y `valor` son el saldo del último movimiento del
 * kardex, copiado acá para listar el stock sin recorrer el historial. Nadie
 * los escribe a mano: los deja `Kardex` cada vez que recalcula.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $numero_parte
 * @property string|null $marca
 * @property int|null $categoria_articulo_id
 * @property UnidadMedida $unidad
 * @property UnidadMedida|null $unidad_compra
 * @property string $factor_compra
 * @property string|null $stock_minimo
 * @property string|null $ubicacion
 * @property list<string>|null $marcas_compatibles
 * @property string|null $observacion
 * @property bool $activo
 * @property string $stock
 * @property string $costo_promedio
 * @property string $valor
 * @property-read CategoriaArticulo|null $categoria
 */
#[Fillable([
    'codigo',
    'nombre',
    'numero_parte',
    'marca',
    'categoria_articulo_id',
    'unidad',
    'unidad_compra',
    'factor_compra',
    'stock_minimo',
    'ubicacion',
    'marcas_compatibles',
    'observacion',
    'activo',
])]
class Articulo extends Model
{
    /** @use HasFactory<ArticuloFactory> */
    use HasFactory, RegistraHistorial;

    /**
     * El código que se le pone a un artículo nuevo si no se escribe uno:
     * «A-00001», correlativo.
     */
    public static function siguienteCodigo(): string
    {
        $siguiente = (int) static::query()->max('id') + 1;

        do {
            $codigo = sprintf('A-%05d', $siguiente++);
        } while (static::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * @return BelongsTo<CategoriaArticulo, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaArticulo::class, 'categoria_articulo_id');
    }

    /**
     * @return HasMany<MovimientoAlmacen, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAlmacen::class);
    }

    /**
     * Por nombre, código, número de parte o marca.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBuscar(Builder $query, string $texto): void
    {
        $like = '%'.mb_strtolower($texto).'%';

        $query->where(function (Builder $query) use ($like): void {
            foreach (['nombre', 'codigo', 'numero_parte', 'marca'] as $campo) {
                $query->orWhereRaw("lower({$campo}) like ?", [$like]);
            }
        });
    }

    /**
     * Los que tienen mínimo y están en él o por debajo.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBajoMinimo(Builder $query): void
    {
        $query->whereNotNull('stock_minimo')->whereColumn('stock', '<=', 'stock_minimo');
    }

    public function estaBajoMinimo(): bool
    {
        return $this->stock_minimo !== null && (float) $this->stock <= (float) $this->stock_minimo;
    }

    /**
     * Cuántas unidades del almacén trae una unidad de compra: 208 litros por
     * cilindro. Sin unidad de compra, 1.
     */
    public function factor(): float
    {
        return $this->unidad_compra === null ? 1.0 : (float) $this->factor_compra;
    }

    public function etiquetaHistorial(): ?string
    {
        return "{$this->codigo} {$this->nombre}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unidad' => UnidadMedida::class,
            'unidad_compra' => UnidadMedida::class,
            'factor_compra' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'marcas_compatibles' => 'array',
            'activo' => 'boolean',
            'stock' => 'decimal:3',
            'costo_promedio' => 'decimal:4',
            'valor' => 'decimal:2',
        ];
    }
}
