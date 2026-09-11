<?php

namespace App\Models;

use App\Enums\EstadoCobranza;
use App\Enums\Moneda;
use App\Services\RelojOperativo;
use Database\Factories\FacturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una factura emitida por el flete. Es la unidad de dinero y, de paso, la que
 * agrupa: un mismo viaje físico puede llegar con dos GR —dos filas en
 * `viajes`— y cobrarse una sola vez, y una factura quincenal puede juntar diez
 * viajes del mismo cliente. Quien factura elige qué filas entran; no se deriva
 * de `Viaje::claveGrupoViaje()`, que es una heurística para contar viajes
 * reales y equivocarse ahí sería equivocarse en la cobranza.
 *
 * @property int $id
 * @property string $numero
 * @property Carbon $fecha_emision
 * @property string|null $monto
 * @property Moneda $moneda
 * @property Carbon|null $fecha_pago
 * @property int|null $cuenta_bancaria_id
 * @property string|null $observacion
 * @property-read CuentaBancaria|null $cuentaBancaria
 * @property-read Collection<int, Viaje> $viajes
 */
#[Fillable([
    'numero',
    'fecha_emision',
    'monto',
    'moneda',
    'fecha_pago',
    'cuenta_bancaria_id',
    'observacion',
])]
class Factura extends Model
{
    /** @use HasFactory<FacturaFactory> */
    use HasFactory;

    /**
     * @return HasMany<Viaje, $this>
     */
    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }

    /**
     * @return BelongsTo<CuentaBancaria, $this>
     */
    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    /**
     * Emitida o ya cobrada. Nunca devuelve `SinFacturar`: si hay factura, algo
     * se facturó — ese caso es el del viaje sin `factura_id`, y lo resuelve
     * `Viaje::estadoCobranza()`.
     */
    public function estado(): EstadoCobranza
    {
        return $this->fecha_pago === null
            ? EstadoCobranza::Facturado
            : EstadoCobranza::Pagado;
    }

    /**
     * Días transcurridos desde la emisión sin cobrar. Null cuando ya se pagó:
     * lo que interesa de una factura cobrada es la fecha, no la antigüedad.
     */
    public function diasVencida(): ?int
    {
        if ($this->fecha_pago !== null) {
            return null;
        }

        return (int) $this->fecha_emision->startOfDay()->diffInDays(RelojOperativo::fechaDeHoy());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date:Y-m-d',
            'fecha_pago' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'moneda' => Moneda::class,
        ];
    }
}
