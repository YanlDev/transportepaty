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
 * Una llanta, con su código, desde que entra al almacén. Sin unidad está en
 * el almacén (o entregada con su vale, todavía sin montar); con unidad y sin
 * `retirada_el` ocupa esa posición; al retirarla, la posición queda libre y
 * la llanta puede montarse en otra unidad.
 *
 * @property int $id
 * @property int|null $vehiculo_id
 * @property int|null $articulo_id
 * @property int|null $detalle_ingreso_id
 * @property int|null $detalle_salida_id
 * @property OrigenLlanta $origen
 * @property string|null $posicion
 * @property string|null $marca
 * @property string|null $medida
 * @property string|null $codigo
 * @property CondicionLlanta $condicion
 * @property string|null $costo
 * @property Carbon|null $instalada_el
 * @property int|null $km_instalacion
 * @property Carbon|null $retirada_el
 * @property int|null $km_retiro
 * @property MotivoRetiroLlanta|null $motivo_retiro
 * @property-read Vehiculo|null $vehiculo
 * @property-read Articulo|null $articulo
 * @property-read DetalleIngreso|null $detalleIngreso
 * @property-read DetalleSalida|null $detalleSalida
 * @property-read Collection<int, MovimientoLlanta> $movimientos
 */
#[Fillable([
    'vehiculo_id', 'articulo_id', 'detalle_ingreso_id', 'detalle_salida_id', 'origen', 'posicion', 'marca', 'medida', 'codigo',
    'condicion', 'costo', 'instalada_el', 'km_instalacion', 'retirada_el', 'km_retiro', 'motivo_retiro',
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
     * La línea de la recepción con que entró al almacén: su lote.
     *
     * @return BelongsTo<DetalleIngreso, $this>
     */
    public function detalleIngreso(): BelongsTo
    {
        return $this->belongsTo(DetalleIngreso::class);
    }

    /**
     * La línea de la entrega con que salió del almacén.
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
        $query->whereNotNull('vehiculo_id')->whereNull('retirada_el');
    }

    /**
     * Las que todavía no se montan: en el almacén o entregadas con su vale.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSinMontar(Builder $query): void
    {
        $query->whereNull('vehiculo_id');
    }

    public function estaMontada(): bool
    {
        return $this->vehiculo_id !== null && $this->retirada_el === null;
    }

    public function estaSinMontar(): bool
    {
        return $this->vehiculo_id === null;
    }

    /**
     * Cómo se nombra: su código y, si los tiene, la marca y la medida.
     */
    public function descripcion(): string
    {
        return trim(implode(' ', array_filter([$this->codigo, $this->marca, $this->medida]))) ?: "Llanta {$this->id}";
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
