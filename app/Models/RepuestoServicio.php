<?php

namespace App\Models;

use Database\Factories\RepuestoServicioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que lleva un paquete del plan: un filtro, los litros de aceite, una
 * calibración. Es la lista que se prepara antes de entrar al taller.
 *
 * @property int $id
 * @property int $servicio_mantenimiento_id
 * @property string $descripcion
 * @property string|null $codigo
 * @property string|null $cantidad
 * @property string|null $unidad
 * @property int $orden
 */
#[Fillable(['servicio_mantenimiento_id', 'descripcion', 'codigo', 'cantidad', 'unidad', 'orden'])]
class RepuestoServicio extends Model
{
    /** @use HasFactory<RepuestoServicioFactory> */
    use HasFactory;

    protected $table = 'repuestos_servicio';

    /**
     * @return BelongsTo<ServicioMantenimiento, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioMantenimiento::class, 'servicio_mantenimiento_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }
}
