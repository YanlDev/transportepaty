<?php

namespace App\Models;

use App\Enums\EstadoInventario;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\InventarioAlmacenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un conteo físico del almacén, estante por estante. Mientras está abierto
 * no toca nada; al cerrarlo, lo contado contra lo que dice el sistema en esa
 * fecha da la diferencia, y esa diferencia entra al kardex. El primer conteo
 * de un artículo es su saldo inicial.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property EstadoInventario $estado
 * @property string|null $observacion
 * @property int|null $user_id
 * @property Carbon|null $cerrado_at
 * @property int|null $cerrado_por
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 */
#[Fillable([
    'fecha',
    'estado',
    'observacion',
    'user_id',
    'cerrado_at',
    'cerrado_por',
    'anulado_at',
    'anulado_por',
    'motivo_anulacion',
])]
class InventarioAlmacen extends Model
{
    /** @use HasFactory<InventarioAlmacenFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    protected $table = 'inventarios_almacen';

    /**
     * «INV-0003»: el número del conteo.
     */
    public function numero(): string
    {
        return sprintf('INV-%04d', $this->id);
    }

    /**
     * @return HasMany<DetalleInventario, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleInventario::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === EstadoInventario::Abierto && ! $this->estaAnulado();
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->numero();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'estado' => EstadoInventario::class,
            'cerrado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
