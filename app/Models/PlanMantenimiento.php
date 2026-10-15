<?php

namespace App\Models;

use Database\Factories\PlanMantenimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El plan preventivo de una marca. Una unidad sigue el plan de su marca
 * (`vehiculos.marca`, comparada en mayúsculas); las marcas sin plan todavía
 * no entran al semáforo, pero sus kilometrajes se registran igual.
 *
 * @property int $id
 * @property string $marca
 * @property string $nombre
 * @property-read Collection<int, ServicioMantenimiento> $servicios
 */
#[Fillable(['marca', 'nombre'])]
class PlanMantenimiento extends Model
{
    /** @use HasFactory<PlanMantenimientoFactory> */
    use HasFactory;

    protected $table = 'planes_mantenimiento';

    /**
     * @return HasMany<ServicioMantenimiento, $this>
     */
    public function servicios(): HasMany
    {
        return $this->hasMany(ServicioMantenimiento::class)->orderBy('nivel');
    }

    public static function deMarca(?string $marca): ?self
    {
        if ($marca === null || trim($marca) === '') {
            return null;
        }

        return self::query()->where('marca', mb_strtoupper(trim($marca)))->first();
    }
}
