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
 * @property int|null $si_lleva_ruta_id Si el ticket también lleva esa ruta, la línea vale `galones_si_la_lleva` (Huaral después del metálico al Callao).
 * @property string|null $galones_si_la_lleva
 * @property bool $tanque_lleno Se llena el tanque (Kio): sin galones fijos; no arma el tope (lo arman las otras líneas) y lo que dice el voucher descuenta de él.
 * @property string|null $monto
 * @property int $orden
 * @property-read PuntoAbastecimiento|null $punto
 */
#[Fillable(['ruta_id', 'variante_ruta_id', 'tipo', 'concepto', 'punto_abastecimiento_id', 'galones', 'si_lleva_ruta_id', 'galones_si_la_lleva', 'tanque_lleno', 'monto', 'orden'])]
class LineaRuta extends Model
{
    use RegistraHistorial;

    protected $table = 'lineas_ruta';

    /**
     * Cómo queda copiada en el ticket. Necesita `punto` cargado.
     *
     * La rebaja por otra ruta solo viaja si la línea la tiene: el ticket la
     * aplica al calcular su tope (ver `RutaVuelta::lineasDeCombustible`).
     *
     * @return array{linea_id: int, tipo: string, concepto: string, punto_id: int|null, punto: string|null, galones: float|null, tanque_lleno: bool, monto: float|null, si_lleva_ruta_id?: int, galones_si_la_lleva?: float}
     */
    public function copia(): array
    {
        $rebaja = $this->si_lleva_ruta_id === null || $this->galones_si_la_lleva === null ? [] : [
            'si_lleva_ruta_id' => $this->si_lleva_ruta_id,
            'galones_si_la_lleva' => (float) $this->galones_si_la_lleva,
        ];

        return [
            'linea_id' => $this->id,
            'tipo' => $this->tipo->value,
            'concepto' => $this->concepto,
            'punto_id' => $this->punto_abastecimiento_id,
            'punto' => $this->punto?->nombre,
            'galones' => $this->galones === null ? null : (float) $this->galones,
            'tanque_lleno' => $this->tanque_lleno,
            'monto' => $this->monto === null ? null : (float) $this->monto,
            ...$rebaja,
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
