<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use Database\Factories\ReglaAbastecimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una tarjeta de la pizarra de abastecimiento: a qué trayecto se aplica y
 * qué le toca (galones por punto, viáticos, peajes).
 *
 * El trayecto se describe con criterios opcionales —sentido, zonas, tipo de
 * carga, lugares, rango de toneladas— y se compara contra cada parada de la
 * vuelta. Si varias tarjetas calzan, gana la más específica (la que pide más
 * cosas). Una tarjeta sin ningún criterio es solo de referencia.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $color
 * @property SentidoTramo|null $sentido
 * @property ZonaRuta|null $zona_origen
 * @property ZonaRuta|null $zona_destino
 * @property TipoCarga|null $tipo_carga
 * @property string|null $lugares Palabras separadas por coma que tienen que aparecer en el origen o el destino.
 * @property string|null $peso_desde Toneladas: más de esto.
 * @property string|null $peso_hasta Toneladas: hasta esto, inclusive.
 * @property string|null $nota
 * @property bool $activa
 * @property int $orden
 * @property-read Collection<int, LineaReglaAbastecimiento> $lineas
 */
#[Fillable([
    'nombre', 'color', 'sentido', 'zona_origen', 'zona_destino', 'tipo_carga',
    'lugares', 'peso_desde', 'peso_hasta', 'nota', 'activa', 'orden',
])]
class ReglaAbastecimiento extends Model
{
    /** @use HasFactory<ReglaAbastecimientoFactory> */
    use HasFactory;

    protected $table = 'reglas_abastecimiento';

    /**
     * @return HasMany<LineaReglaAbastecimiento, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaReglaAbastecimiento::class)->orderBy('orden');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('id');
    }

    /**
     * Cuántas cosas pide: es lo que decide entre dos tarjetas que calzan con
     * la misma parada. Cero: no se aplica sola.
     */
    public function especificidad(): int
    {
        return count(array_filter([
            $this->sentido, $this->zona_origen, $this->zona_destino, $this->tipo_carga,
            $this->lugares, $this->peso_desde, $this->peso_hasta,
        ], fn (mixed $criterio): bool => $criterio !== null && $criterio !== ''));
    }

    /**
     * Si la tarjeta describe esta parada. `$texto` es todo lo que se sabe del
     * lugar (los distritos de la parada y las direcciones de sus GR) y
     * `$toneladas` el peso de sus GR, si hay.
     */
    public function aplicaA(ParadaVuelta $parada, string $texto, ?float $toneladas): bool
    {
        if ($this->especificidad() === 0) {
            return false;
        }

        $coincide = fn (mixed $criterio, mixed $valor): bool => $criterio === null || $criterio === $valor;

        if (! $coincide($this->sentido, $parada->sentido)
            || ! $coincide($this->zona_origen, $parada->zona_origen)
            || ! $coincide($this->zona_destino, $parada->zona_destino)
            || ! $coincide($this->tipo_carga, $parada->tipo_carga)) {
            return false;
        }

        if (filled($this->lugares)) {
            $texto = mb_strtoupper($texto);
            $alguno = collect(explode(',', $this->lugares))
                ->map(fn (string $lugar): string => mb_strtoupper(trim($lugar)))
                ->filter()
                ->contains(fn (string $lugar): bool => str_contains($texto, $lugar));

            if (! $alguno) {
                return false;
            }
        }

        if ($this->peso_desde !== null || $this->peso_hasta !== null) {
            if ($toneladas === null) {
                return false;
            }

            if ($this->peso_desde !== null && $toneladas <= (float) $this->peso_desde) {
                return false;
            }

            if ($this->peso_hasta !== null && $toneladas > (float) $this->peso_hasta) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentido' => SentidoTramo::class,
            'zona_origen' => ZonaRuta::class,
            'zona_destino' => ZonaRuta::class,
            'tipo_carga' => TipoCarga::class,
            'peso_desde' => 'decimal:2',
            'peso_hasta' => 'decimal:2',
            'activa' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
