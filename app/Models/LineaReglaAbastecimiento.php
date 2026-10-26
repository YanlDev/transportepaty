<?php

namespace App\Models;

use App\Enums\TipoLineaAbastecimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de una tarjeta de la pizarra: «Mina · 100 gal», «Tanque full en
 * Kio», «Viático Lima · S/ 700».
 *
 * @property int $id
 * @property int $regla_abastecimiento_id
 * @property TipoLineaAbastecimiento $tipo
 * @property string $concepto
 * @property int|null $punto_abastecimiento_id
 * @property string|null $galones
 * @property bool $tanque_lleno
 * @property string|null $monto
 * @property int $orden
 * @property-read PuntoAbastecimiento|null $punto
 */
#[Fillable(['regla_abastecimiento_id', 'tipo', 'concepto', 'punto_abastecimiento_id', 'galones', 'tanque_lleno', 'monto', 'orden'])]
class LineaReglaAbastecimiento extends Model
{
    protected $table = 'lineas_regla_abastecimiento';

    /**
     * @return BelongsTo<PuntoAbastecimiento, $this>
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoAbastecimiento::class, 'punto_abastecimiento_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoLineaAbastecimiento::class,
            'galones' => 'decimal:2',
            'tanque_lleno' => 'boolean',
            'monto' => 'decimal:2',
            'orden' => 'integer',
        ];
    }
}
