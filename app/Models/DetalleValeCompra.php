<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una línea de lo que el vale autoriza a comprar: un artículo del almacén o,
 * sin artículo, el servicio de un tercero.
 *
 * @property int $id
 * @property int $vale_compra_id
 * @property int|null $articulo_id
 * @property string $descripcion
 * @property bool $es_servicio
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $total
 * @property Carbon|null $atendido_at
 * @property int|null $atendido_por
 * @property-read ValeCompra $vale
 * @property-read Articulo|null $articulo
 */
#[Fillable(['vale_compra_id', 'articulo_id', 'descripcion', 'es_servicio', 'cantidad', 'precio_unitario', 'total', 'atendido_at', 'atendido_por'])]
class DetalleValeCompra extends Model
{
    protected $table = 'detalles_vale_compra';

    /**
     * @return BelongsTo<ValeCompra, $this>
     */
    public function vale(): BelongsTo
    {
        return $this->belongsTo(ValeCompra::class, 'vale_compra_id');
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_servicio' => 'boolean',
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'total' => 'decimal:2',
            'atendido_at' => 'datetime',
        ];
    }
}
