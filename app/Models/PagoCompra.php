<?php

namespace App\Models;

use App\Enums\MedioPago;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use Database\Factories\PagoCompraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un pago hecho al proveedor por un pedido de compra. Es una anotación: el
 * dinero se movió en el banco (o en efectivo) y acá queda cuándo, cuánto, de
 * qué cuenta y con qué voucher. No se borra: se anula con su motivo.
 *
 * @property int $id
 * @property int $vale_compra_id
 * @property Carbon $fecha
 * @property string $monto
 * @property MedioPago $medio
 * @property int|null $cuenta_bancaria_id
 * @property string|null $numero_operacion
 * @property int|null $voucher_id
 * @property string|null $observacion
 * @property int|null $user_id
 * @property-read ValeCompra $vale
 * @property-read CuentaBancaria|null $cuenta
 * @property-read User|null $user
 */
#[Fillable([
    'vale_compra_id', 'fecha', 'monto', 'medio', 'cuenta_bancaria_id', 'numero_operacion', 'voucher_id',
    'observacion', 'user_id', 'anulado_at', 'anulado_por', 'motivo_anulacion',
])]
class PagoCompra extends Model
{
    /** @use HasFactory<PagoCompraFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    protected $table = 'pagos_compra';

    /**
     * @return BelongsTo<ValeCompra, $this>
     */
    public function vale(): BelongsTo
    {
        return $this->belongsTo(ValeCompra::class, 'vale_compra_id');
    }

    /**
     * La cuenta de la empresa de la que salió el pago.
     *
     * @return BelongsTo<CuentaBancaria, $this>
     */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'medio' => MedioPago::class,
            'anulado_at' => 'datetime',
        ];
    }
}
