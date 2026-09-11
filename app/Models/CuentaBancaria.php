<?php

namespace App\Models;

use App\Enums\Moneda;
use Database\Factories\CuentaBancariaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una cuenta de la empresa. Existe para que la columna «entidad» de la
 * cobranza sea una elección de una lista y no texto libre: escrito a mano, el
 * mismo banco termina como «BCP», «Bcp» y «Banco de Crédito», y con eso no se
 * puede filtrar ni totalizar por dónde entró la plata.
 *
 * @property int $id
 * @property string $banco
 * @property string $alias
 * @property string $numero_cuenta
 * @property string|null $cci
 * @property Moneda $moneda
 * @property bool $activa
 * @property string|null $notas
 */
#[Fillable([
    'banco',
    'alias',
    'numero_cuenta',
    'cci',
    'moneda',
    'activa',
    'notas',
])]
class CuentaBancaria extends Model
{
    /** @use HasFactory<CuentaBancariaFactory> */
    use HasFactory;

    protected $table = 'cuentas_bancarias';

    /**
     * @return HasMany<Factura, $this>
     */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    /**
     * Cómo se identifica la cuenta cuando hay que distinguirla de otra del
     * mismo banco: el alias solo no alcanza si abrieron dos en soles.
     */
    public function descripcion(): string
    {
        return "{$this->alias} · {$this->numero_cuenta}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moneda' => Moneda::class,
            'activa' => 'boolean',
        ];
    }
}
