<?php

namespace App\Models;

use App\Enums\CondicionLlanta;
use App\Enums\MotivoRetiroLlanta;
use App\Enums\OrigenLlanta;
use App\Models\Concerns\RegistraHistorial;
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
 * @property int|null $articulo_id
 * @property int|null $detalle_salida_id
 * @property OrigenLlanta $origen
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
    'vehiculo_id', 'articulo_id', 'detalle_salida_id', 'origen', 'posicion', 'marca', 'medida', 'codigo', 'condicion',
    'instalada_el', 'km_instalacion', 'retirada_el', 'km_retiro', 'motivo_retiro',
])]
class Llanta extends Model
{
    /** @use HasFactory<LlantaFactory> */
    use HasFactory, RegistraHistorial;

    public function etiquetaHistorial(): ?string
    {
        return $this->codigo;
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * El artículo del almacén del que es esta llanta.
     *
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * La línea del vale de salida con que salió del almacén.
     *
     * @return BelongsTo<DetalleSalida, $this>
     */
    public function detalleSalida(): BelongsTo
    {
        return $this->belongsTo(DetalleSalida::class);
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
            'origen' => OrigenLlanta::class,
            'condicion' => CondicionLlanta::class,
            'instalada_el' => 'date',
            'km_instalacion' => 'integer',
            'retirada_el' => 'date',
            'km_retiro' => 'integer',
            'motivo_retiro' => MotivoRetiroLlanta::class,
        ];
    }
}
