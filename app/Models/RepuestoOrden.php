<?php

namespace App\Models;

use Database\Factories\RepuestoOrdenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un repuesto que el mecánico pide para una orden: del catálogo del almacén
 * si existe ahí, o en texto si no. Es un pedido, no un movimiento: lo que
 * de verdad salió del almacén son los vales a nombre de la orden.
 *
 * Los del requerimiento se aprueban con él; los que se piden después
 * («adicionales») los aprueba operaciones uno por uno.
 *
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int|null $articulo_id
 * @property string $descripcion
 * @property string $cantidad
 * @property string|null $unidad
 * @property bool $adicional
 * @property Carbon|null $aprobado_at
 * @property int|null $aprobado_por
 * @property int|null $user_id
 * @property int|null $registrado_por
 * @property-read OrdenTrabajo $orden
 * @property-read Articulo|null $articulo
 * @property-read User|null $pedidoPor
 * @property-read User|null $registradoPor
 */
#[Fillable(['orden_trabajo_id', 'articulo_id', 'descripcion', 'cantidad', 'unidad', 'adicional', 'aprobado_at', 'aprobado_por', 'user_id', 'registrado_por'])]
class RepuestoOrden extends Model
{
    /** @use HasFactory<RepuestoOrdenFactory> */
    use HasFactory;

    protected $table = 'repuestos_orden';

    /**
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pedidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quien lo anotó por el mecánico, cuando no lo pidió él desde su usuario.
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function estaAprobado(): bool
    {
        return $this->aprobado_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'adicional' => 'boolean',
            'aprobado_at' => 'datetime',
        ];
    }
}
