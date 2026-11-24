<?php

namespace App\Models;

use App\Enums\AreaGasto;
use App\Enums\EstadoValeCompra;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\ValeGastoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un vale de gasto: un área pide plata para algo que no pasa por el almacén,
 * gerencia lo aprueba (o lo rechaza) y recién entonces se gasta. Dice qué
 * área pide, en qué categoría cae y para qué es, que es lo que después
 * permite ver los egresos de la empresa por área.
 *
 * No se borra: se anula con su motivo.
 *
 * @property int $id
 * @property EstadoValeCompra $estado
 * @property AreaGasto $area
 * @property int $categoria_gasto_id
 * @property string $concepto Qué se va a pagar.
 * @property string $motivo Para qué.
 * @property string $monto En soles.
 * @property string $beneficiario A quién se le paga.
 * @property int|null $vehiculo_id La unidad del gasto; null si es general del área.
 * @property int|null $solicitado_por
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_at
 * @property int|null $rechazado_por
 * @property Carbon|null $rechazado_at
 * @property string|null $motivo_rechazo
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property Carbon|null $created_at
 * @property-read CategoriaGasto $categoria
 * @property-read Vehiculo|null $vehiculo
 * @property-read User|null $solicitante
 * @property-read User|null $aprobador
 * @property-read User|null $rechazadoPor
 */
#[Fillable([
    'estado', 'area', 'categoria_gasto_id', 'concepto', 'motivo', 'monto', 'beneficiario', 'vehiculo_id',
    'solicitado_por', 'aprobado_por', 'aprobado_at', 'rechazado_por', 'rechazado_at', 'motivo_rechazo',
    'anulado_at', 'anulado_por', 'motivo_anulacion',
])]
class ValeGasto extends Model implements HasMedia
{
    /** @use HasFactory<ValeGastoFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    use InteractsWithMedia;

    protected $table = 'vales_gasto';

    public function numero(): string
    {
        return sprintf('VG-%06d', $this->id);
    }

    /**
     * Cómo se lee el estado: anulado manda sobre lo demás.
     */
    public function situacion(): string
    {
        return $this->estaAnulado() ? 'Anulado' : $this->estado->label();
    }

    /**
     * @return BelongsTo<CategoriaGasto, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_gasto_id');
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    /**
     * El sustento (una cotización, una foto), en el disco privado: solo se
     * abre con sesión y permiso.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('sustento')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
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
            'estado' => EstadoValeCompra::class,
            'area' => AreaGasto::class,
            'monto' => 'decimal:2',
            'aprobado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
