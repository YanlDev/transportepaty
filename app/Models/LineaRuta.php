<?php

namespace App\Models;

use App\Enums\TipoLineaAbastecimiento;
use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de la dotación de una ruta: «Mina · 100 gal», «Viático Lima ·
 * S/ 700». Sin variante vale para toda la ruta; con variante, solo para ella.
 *
 * @property int $id
 * @property int $ruta_id
 * @property int|null $variante_ruta_id
 * @property TipoLineaAbastecimiento $tipo
 * @property string $concepto
 * @property int|null $punto_abastecimiento_id
 * @property string|null $galones Null: por definir (o tanque full).
 * @property bool $tanque_lleno Se llena el tanque (Kio): sin galones fijos, lo dice el voucher; no suma al tope.
 * @property string|null $monto
 * @property int $orden
 * @property-read PuntoAbastecimiento|null $punto
 */
#[Fillable(['ruta_id', 'variante_ruta_id', 'tipo', 'concepto', 'punto_abastecimiento_id', 'galones', 'tanque_lleno', 'monto', 'orden'])]
class LineaRuta extends Model
{
    use RegistraHistorial;

    protected $table = 'lineas_ruta';

    /**
     * Cómo queda copiada en el ticket. Necesita `punto` cargado.
     *
     * @return array{linea_id: int, tipo: string, concepto: string, punto_id: int|null, punto: string|null, galones: float|null, tanque_lleno: bool, monto: float|null}
     */
    public function copia(): array
    {
        return [
            'linea_id' => $this->id,
            'tipo' => $this->tipo->value,
            'concepto' => $this->concepto,
            'punto_id' => $this->punto_abastecimiento_id,
            'punto' => $this->punto?->nombre,
            'galones' => $this->galones === null ? null : (float) $this->galones,
            'tanque_lleno' => $this->tanque_lleno,
            'monto' => $this->monto === null ? null : (float) $this->monto,
        ];
    }

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
