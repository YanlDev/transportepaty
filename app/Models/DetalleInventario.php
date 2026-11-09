<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Lo que se contó de un artículo. Si sobra, entra al costo que se anote (o
 * al promedio si no se anota); si falta, sale al promedio. Lo que no tiene
 * factura se marca como costo estimado.
 *
 * @property int $id
 * @property int $inventario_almacen_id
 * @property int $articulo_id
 * @property string $cantidad_contada
 * @property string|null $costo_unitario
 * @property bool $costo_estimado
 * @property string|null $stock_sistema
 * @property string|null $diferencia
 * @property string|null $observacion
 * @property-read InventarioAlmacen $inventario
 * @property-read Articulo $articulo
 */
#[Fillable([
    'inventario_almacen_id',
    'articulo_id',
    'cantidad_contada',
    'costo_unitario',
    'costo_estimado',
    'stock_sistema',
    'diferencia',
    'observacion',
])]
class DetalleInventario extends Model
{
    protected $table = 'detalles_inventario';

    /**
     * @return BelongsTo<InventarioAlmacen, $this>
     */
    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioAlmacen::class, 'inventario_almacen_id');
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return MorphOne<MovimientoAlmacen, $this>
     */
    public function movimiento(): MorphOne
    {
        return $this->morphOne(MovimientoAlmacen::class, 'origen');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_contada' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'costo_estimado' => 'boolean',
            'stock_sistema' => 'decimal:3',
            'diferencia' => 'decimal:3',
        ];
    }
}
