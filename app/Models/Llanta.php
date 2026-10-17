<?php

namespace App\Models;

use App\Enums\CondicionLlanta;
use App\Enums\MotivoRetiroLlanta;
use Database\Factories\LlantaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una llanta montada en una posición de la unidad. Mientras `retirada_el`
 * está vacío, ocupa esa posición; al retirarla, la posición queda libre.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property string $posicion
 * @property string|null $marca
 * @property string|null $medida
 * @property string|null $codigo
 * @property CondicionLlanta $condicion
 * @property Carbon $instalada_el
 * @property int|null $km_instalacion
 * @property Carbon|null $retirada_el
 * @property int|null $km_retiro
 * @property MotivoRetiroLlanta|null $motivo_retiro
 * @property-read Vehiculo $vehiculo
 * @property-read Collection<int, MovimientoLlanta> $movimientos
 */
#[Fillable([
    'vehiculo_id', 'posicion', 'marca', 'medida', 'codigo', 'condicion',
    'instalada_el', 'km_instalacion', 'retirada_el', 'km_retiro', 'motivo_retiro',
])]
class Llanta extends Model
{
    /** @use HasFactory<LlantaFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return HasMany<MovimientoLlanta, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoLlanta::class);
    }

    /**
     * Las que están puestas en la unidad.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMontadas(Builder $query): void
    {
        $query->whereNull('retirada_el');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condicion' => CondicionLlanta::class,
            'instalada_el' => 'date',
            'km_instalacion' => 'integer',
            'retirada_el' => 'date',
            'km_retiro' => 'integer',
            'motivo_retiro' => MotivoRetiroLlanta::class,
        ];
    }
}
