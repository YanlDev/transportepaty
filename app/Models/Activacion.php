<?php

namespace App\Models;

use App\Enums\ResultadoActivacion;
use Database\Factories\ActivacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vehiculo_id
 * @property int|null $conductor_id
 * @property int|null $registrada_por
 * @property Carbon $fecha
 * @property int|null $kilometraje
 * @property ResultadoActivacion $resultado
 * @property string|null $observaciones
 */
#[Fillable([
    'vehiculo_id',
    'conductor_id',
    'registrada_por',
    'fecha',
    'kilometraje',
    'resultado',
    'observaciones',
])]
class Activacion extends Model
{
    /** @use HasFactory<ActivacionFactory> */
    use HasFactory;

    protected $table = 'activaciones';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
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
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'kilometraje' => 'integer',
            'resultado' => ResultadoActivacion::class,
        ];
    }
}
