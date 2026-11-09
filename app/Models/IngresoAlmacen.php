<?php

namespace App\Models;

use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Enums\TipoIngreso;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\IngresoAlmacenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Lo que entra al almacén. Una compra trae su comprobante tal cual (moneda,
 * IGV, totales) y además lo que vale para el kardex: en soles y, si es
 * factura, sin IGV. Una devolución es lo que vuelve de un vale, al mismo
 * costo con que salió.
 *
 * @property int $id
 * @property TipoIngreso $tipo
 * @property Carbon $fecha
 * @property int|null $proveedor_id
 * @property TipoComprobante|null $tipo_comprobante
 * @property string|null $serie_numero
 * @property Moneda $moneda
 * @property string|null $tipo_cambio
 * @property bool $precios_con_igv
 * @property string $subtotal
 * @property string $igv
 * @property string $total
 * @property string $costo_total
 * @property int|null $salida_almacen_id
 * @property string|null $observacion
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property-read Proveedor|null $proveedor
 * @property-read SalidaAlmacen|null $salida
 */
#[Fillable([
    'tipo',
    'fecha',
    'proveedor_id',
    'tipo_comprobante',
    'serie_numero',
    'moneda',
    'tipo_cambio',
    'precios_con_igv',
    'subtotal',
    'igv',
    'total',
    'costo_total',
    'salida_almacen_id',
    'observacion',
    'user_id',
    'anulado_at',
    'anulado_por',
    'motivo_anulacion',
])]
class IngresoAlmacen extends Model implements HasMedia
{
    /** @use HasFactory<IngresoAlmacenFactory> */
    use HasFactory, InteractsWithMedia, RegistraHistorial, SeAnula;

    protected $table = 'ingresos_almacen';

    /**
     * «IN-000012»: el número del ingreso.
     */
    public function numero(): string
    {
        return sprintf('IN-%06d', $this->id);
    }

    /**
     * Cómo se nombra en el kardex: el comprobante si tiene, si no el número.
     */
    public function documento(): string
    {
        if ($this->tipo === TipoIngreso::Devolucion) {
            return $this->numero().' · Dev. '.($this->salida?->numero() ?? '');
        }

        return $this->serie_numero !== null && $this->tipo_comprobante !== null
            ? "{$this->tipo_comprobante->abreviatura()} {$this->serie_numero}"
            : $this->numero();
    }

    /**
     * @return HasMany<DetalleIngreso, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleIngreso::class);
    }

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * El vale del que vuelve, en una devolución.
     *
     * @return BelongsTo<SalidaAlmacen, $this>
     */
    public function salida(): BelongsTo
    {
        return $this->belongsTo(SalidaAlmacen::class, 'salida_almacen_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El comprobante escaneado o el PDF que mandó el proveedor.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comprobante')
            ->singleFile()
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
            'tipo' => TipoIngreso::class,
            'fecha' => 'date:Y-m-d',
            'tipo_comprobante' => TipoComprobante::class,
            'moneda' => Moneda::class,
            'tipo_cambio' => 'decimal:4',
            'precios_con_igv' => 'boolean',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'anulado_at' => 'datetime',
        ];
    }
}
