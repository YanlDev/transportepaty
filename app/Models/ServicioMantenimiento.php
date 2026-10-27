<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\ServicioMantenimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un paquete del plan, con su intervalo. Los paquetes van anidados por
 * `nivel`: hacer uno cuenta como hacer todos los de nivel menor o igual,
 * porque los incluye (la L de Scania lleva lo de la M, la S y la X).
 *
 * @property int $id
 * @property int $plan_mantenimiento_id
 * @property string $codigo
 * @property string $nombre
 * @property int $nivel
 * @property int $intervalo_km
 * @property int|null $intervalo_meses
 * @property-read PlanMantenimiento $plan
 * @property-read Collection<int, RepuestoServicio> $repuestos
 */
#[Fillable(['plan_mantenimiento_id', 'codigo', 'nombre', 'nivel', 'intervalo_km', 'intervalo_meses'])]
class ServicioMantenimiento extends Model
{
    /** @use HasFactory<ServicioMantenimientoFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'servicios_mantenimiento';

    /**
     * @return BelongsTo<PlanMantenimiento, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_mantenimiento_id');
    }

    /**
     * @return HasMany<RepuestoServicio, $this>
     */
    public function repuestos(): HasMany
    {
        return $this->hasMany(RepuestoServicio::class)->orderBy('orden');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'intervalo_km' => 'integer',
            'intervalo_meses' => 'integer',
        ];
    }
}
