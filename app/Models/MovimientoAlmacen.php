<?php

namespace App\Models;

use App\Enums\TipoMovimientoAlmacen;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Una fila del kardex: un artículo que entra o sale por una línea de un
 * documento, con el saldo que deja. Los saldos los escribe `Kardex` al
 * recalcular, en orden de fecha y de registro.
 *
 * @property int $id
 * @property int $articulo_id
 * @property Carbon $fecha
 * @property TipoMovimientoAlmacen $tipo
 * @property string $origen_type
 * @property int $origen_id
 * @property string $documento
 * @property string $entrada
 * @property string $salida
 * @property string $costo_unitario
 * @property string $costo_total
 * @property string $saldo_cantidad
 * @property string $saldo_costo_unitario
 * @property string $saldo_valor
 * @property int|null $vehiculo_id
 * @property int|null $orden_trabajo_id
 * @property int|null $proveedor_id
 * @property int|null $user_id
 * @property-read Articulo $articulo
 * @property-read DetalleIngreso|DetalleSalida|DetalleInventario $origen
 * @property-read Vehiculo|null $vehiculo
 */
#[Fillable([
    'articulo_id',
    'fecha',
    'tipo',
    'origen_type',
    'origen_id',
    'documento',
    'entrada',
    'salida',
    'costo_unitario',
    'costo_total',
    'saldo_cantidad',
    'saldo_costo_unitario',
    'saldo_valor',
    'vehiculo_id',
    'orden_trabajo_id',
    'proveedor_id',
    'user_id',
])]
class MovimientoAlmacen extends Model
{
    protected $table = 'movimientos_almacen';

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'tipo' => TipoMovimientoAlmacen::class,
            'entrada' => 'decimal:3',
            'salida' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'costo_total' => 'decimal:2',
            'saldo_cantidad' => 'decimal:3',
            'saldo_costo_unitario' => 'decimal:4',
            'saldo_valor' => 'decimal:2',
        ];
    }
}
