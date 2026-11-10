<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Una línea del ingreso. Una bonificación es lo que el proveedor regala: su
 * precio es el valor con que entra al almacén, pero no suma al comprobante.
 *
 * Una línea del ingreso. `cantidad` y `precio_unitario` son las del
 * comprobante (2 cilindros a S/ 1,500); `cantidad_base` y `costo_unitario`,
 * las del almacén (416 litros a S/ 7.21 sin IGV).
 *
 * @property int $id
 * @property int $ingreso_almacen_id
 * @property int $articulo_id
 * @property int|null $detalle_salida_id
 * @property bool $bonificacion
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $factor
 * @property string $cantidad_base
 * @property string $costo_unitario
 * @property string $costo_total
 * @property Carbon|null $vence_el
 * @property-read IngresoAlmacen $ingreso
 * @property-read Articulo $articulo
 * @property-read DetalleSalida|null $detalleSalida
 */
#[Fillable([
    'ingreso_almacen_id',
    'articulo_id',
    'detalle_salida_id',
    'vence_el',
    'bonificacion',
    'cantidad',
    'precio_unitario',
    'factor',
    'cantidad_base',
    'costo_unitario',
    'costo_total',
])]
class DetalleIngreso extends Model
{
    protected $table = 'detalles_ingreso';

    /**
     * @return BelongsTo<IngresoAlmacen, $this>
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(IngresoAlmacen::class, 'ingreso_almacen_id');
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return BelongsTo<DetalleSalida, $this>
     */
    public function detalleSalida(): BelongsTo
    {
        return $this->belongsTo(DetalleSalida::class);
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
            'bonificacion' => 'boolean',
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'factor' => 'decimal:3',
            'cantidad_base' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'costo_total' => 'decimal:2',
            'vence_el' => 'date:Y-m-d',
        ];
    }
}
