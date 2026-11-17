<?php

namespace App\Models;

use App\Enums\Moneda;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\CobroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Un cobro: la plata que entró, como figura en el banco (o en la caja). Un
 * depósito con su fecha, su cuenta, su N° de operación y su monto, que se
 * reparte entre una o varias facturas. Lo que no se repartió es saldo a
 * favor del cliente: un adelanto que se aplica cuando salga su factura.
 *
 * No se borra: se anula con su motivo, y deja de contar.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int|null $cuenta_bancaria_id
 * @property string|null $numero_operacion
 * @property string $monto
 * @property Moneda $moneda
 * @property int|null $cliente_id
 * @property string|null $observacion
 * @property int|null $user_id
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property-read CuentaBancaria|null $cuentaBancaria
 * @property-read Cliente|null $cliente
 * @property-read User|null $usuario
 * @property-read User|null $anuladoPor
 * @property-read Collection<int, Factura> $facturas Con `pivot.monto`: lo que este cobro le pagó a cada una.
 * @property-read Pivot|null $pivot Cuando se carga desde una factura: `monto` es lo que le pagó.
 */
#[Fillable(['fecha', 'cuenta_bancaria_id', 'numero_operacion', 'monto', 'moneda', 'cliente_id', 'observacion', 'user_id'])]
class Cobro extends Model
{
    /** @use HasFactory<CobroFactory> */
    use HasFactory, RegistraHistorial;

    public const SIN_ANULADOS = 'sin-anulados';

    public function etiquetaHistorial(): ?string
    {
        return 'Cobro del '.$this->fecha->format('d/m/Y').($this->numero_operacion === null ? '' : " · op. {$this->numero_operacion}");
    }

    protected static function booted(): void
    {
        // Un cobro anulado no es plata: no cuenta en ninguna factura.
        static::addGlobalScope(self::SIN_ANULADOS, function (Builder $query): void {
            $query->whereNull($query->qualifyColumn('anulado_at'));
        });

        // Con el mes cerrado, sus cobros no se registran, corrigen ni anulan.
        static::saving(function (Cobro $cobro): void {
            if ($cobro->isDirty()) {
                CierreDeMes::exigirAbierto('Cobro', $cobro->getOriginal('fecha'), $cobro->fecha);
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeConAnulados(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::SIN_ANULADOS);
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /**
     * Lo repartido entre sus facturas. Necesita `facturas` cargadas.
     */
    public function aplicado(): float
    {
        return round((float) $this->facturas->sum(fn (Factura $factura): float => $factura->montoDelCobro()), 2);
    }

    /**
     * Lo que todavía no paga ninguna factura: el saldo a favor del cliente.
     * Necesita `facturas` cargadas.
     */
    public function saldoAFavor(): float
    {
        return $this->estaAnulado() ? 0.0 : round((float) $this->monto - $this->aplicado(), 2);
    }

    /**
     * Lo que este cobro le pagó a la factura desde la que se cargó
     * (`$factura->cobros`). Cero si no vino por esa relación.
     */
    public function montoEnLaFactura(): float
    {
        return (float) $this->getRelationValue('pivot')?->getAttribute('monto');
    }

    /**
     * @return BelongsToMany<Factura, $this>
     */
    public function facturas(): BelongsToMany
    {
        return $this->belongsToMany(Factura::class)->withPivot('monto')->withTimestamps();
    }

    /**
     * @return BelongsTo<CuentaBancaria, $this>
     */
    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'moneda' => Moneda::class,
            'anulado_at' => 'datetime',
        ];
    }
}
