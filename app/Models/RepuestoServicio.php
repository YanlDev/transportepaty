<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
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
 * @property int|null $articulo_id
 * @property bool $es_servicio
 * @property string $descripcion
 * @property string|null $codigo
 * @property string|null $cantidad
 * @property string|null $unidad
 * @property int $orden
 * @property-read Articulo|null $articulo
 */
#[Fillable(['servicio_mantenimiento_id', 'articulo_id', 'es_servicio', 'descripcion', 'codigo', 'cantidad', 'unidad', 'orden'])]
class RepuestoServicio extends Model
{
    /** @use HasFactory<RepuestoServicioFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'repuestos_servicio';

    /**
     * @return BelongsTo<ServicioMantenimiento, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioMantenimiento::class, 'servicio_mantenimiento_id');
    }

    /**
     * El artículo del almacén que cubre este ítem. Sin él no se puede
     * comprobar que salió con vale.
     *
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
            'cantidad' => 'decimal:2',
            'es_servicio' => 'boolean',
        ];
    }
}
