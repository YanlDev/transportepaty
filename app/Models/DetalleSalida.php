<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Una línea del vale de salida. Su costo lo pone `Kardex`: el promedio del
 * artículo en la fecha del vale.
 *
 * @property int $id
 * @property int $salida_almacen_id
 * @property int $articulo_id
 * @property string $cantidad
 * @property string $costo_unitario
 * @property string $costo_total
 * @property-read SalidaAlmacen $salida
 * @property-read Articulo $articulo
 */
#[Fillable(['salida_almacen_id', 'articulo_id', 'cantidad', 'costo_unitario', 'costo_total'])]
class DetalleSalida extends Model
{
    protected $table = 'detalles_salida';

    /**
     * @return BelongsTo<SalidaAlmacen, $this>
     */
    public function salida(): BelongsTo
    {
        return $this->belongsTo(SalidaAlmacen::class, 'salida_almacen_id');
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * Las líneas de devolución que vuelven de esta.
     *
     * @return HasMany<DetalleIngreso, $this>
     */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(DetalleIngreso::class);
    }

    /**
     * @return MorphOne<MovimientoAlmacen, $this>
     */
    public function movimiento(): MorphOne
    {
        return $this->morphOne(MovimientoAlmacen::class, 'origen');
    }

    /**
     * Cuánto de esta línea ya volvió al almacén, sin contar devoluciones
     * anuladas.
     */
    public function cantidadDevuelta(): float
    {
        return (float) $this->devoluciones()
            ->whereHas('ingreso', fn ($query) => $query->whereNull('anulado_at'))
            ->sum('cantidad_base');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }
}
