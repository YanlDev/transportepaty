<?php

namespace App\Models;

use Database\Factories\AbastecimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Combustible que abastecimiento autorizó para una unidad (por el QR del
 * convenio con Repsol, o en Kio): cuántos galones, en qué punto y para qué
 * vuelta.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property int|null $vuelta_id
 * @property int|null $punto_abastecimiento_id
 * @property int|null $conductor_id
 * @property Carbon $fecha
 * @property string $galones
 * @property bool $tanque_lleno
 * @property string|null $precio_galon Soles por galón en ese grifo.
 * @property string|null $importe Galones × precio, en soles.
 * @property string|null $observacion
 * @property int|null $user_id
 * @property-read Vehiculo $vehiculo
 * @property-read Vuelta|null $vuelta
 * @property-read PuntoAbastecimiento|null $punto
 * @property-read Conductor|null $conductor
 * @property-read User|null $registradoPor
 */
#[Fillable([
    'vehiculo_id', 'vuelta_id', 'punto_abastecimiento_id', 'conductor_id', 'fecha',
    'galones', 'tanque_lleno', 'precio_galon', 'importe', 'observacion', 'user_id',
])]
class Abastecimiento extends Model
{
    /** @use HasFactory<AbastecimientoFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return BelongsTo<PuntoAbastecimiento, $this>
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoAbastecimiento::class, 'punto_abastecimiento_id');
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'galones' => 'decimal:2',
            'tanque_lleno' => 'boolean',
            'precio_galon' => 'decimal:3',
            'importe' => 'decimal:2',
        ];
    }
}
