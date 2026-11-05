<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\DotacionTramoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La tarjeta de la pizarra que se confirmó para un tramo (ida o vuelta) de un
 * ticket, copiada como estaba al confirmarla. Es lo autorizado: si la
 * pizarra cambia después, esto no.
 *
 * @phpstan-type LineaDotacion array{linea_id: int|null, tipo: string, concepto: string, punto_id: int|null, punto: string|null, galones: float|null, monto: float|null}
 *
 * @property int $id
 * @property int $vuelta_id
 * @property SentidoTramo $sentido
 * @property int|null $regla_abastecimiento_id
 * @property string $nombre
 * @property string|null $color
 * @property list<LineaDotacion> $lineas
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vuelta $vuelta
 * @property-read User|null $usuario
 */
#[Fillable(['vuelta_id', 'sentido', 'regla_abastecimiento_id', 'nombre', 'color', 'lineas', 'user_id'])]
class DotacionTramo extends Model
{
    /** @use HasFactory<DotacionTramoFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'dotaciones_tramo';

    public function etiquetaHistorial(): ?string
    {
        return Vuelta::query()->whereKey($this->vuelta_id)->value('codigo').' · '.$this->sentido->label().' · '.$this->nombre;
    }

    /**
     * Copia la tarjeta como está ahora. Necesita `lineas.punto` cargado.
     *
     * @return list<LineaDotacion>
     */
    public static function lineasDe(ReglaAbastecimiento $regla): array
    {
        return array_values($regla->lineas
            ->map(fn (LineaReglaAbastecimiento $linea): array => [
                'linea_id' => $linea->id,
                'tipo' => $linea->tipo->value,
                'concepto' => $linea->concepto,
                'punto_id' => $linea->punto_abastecimiento_id,
                'punto' => $linea->punto?->nombre,
                'galones' => $linea->galones === null ? null : (float) $linea->galones,
                'monto' => $linea->monto === null ? null : (float) $linea->monto,
            ])
            ->all());
    }

    /**
     * Las líneas de combustible.
     *
     * @return list<LineaDotacion>
     */
    public function lineasDeCombustible(): array
    {
        return array_values(array_filter(
            $this->lineas,
            fn (array $linea): bool => $linea['tipo'] === TipoLineaAbastecimiento::Combustible->value,
        ));
    }

    /**
     * Los viáticos y peajes.
     *
     * @return list<LineaDotacion>
     */
    public function lineasDeViaticos(): array
    {
        return array_values(array_filter(
            $this->lineas,
            fn (array $linea): bool => $linea['tipo'] !== TipoLineaAbastecimiento::Combustible->value,
        ));
    }

    /**
     * El tope de combustible del tramo: la suma de sus galones. Null si una
     * línea quedó sin galones (por definir): sumar sin ella engañaría.
     */
    public function tope(): ?float
    {
        $galones = array_column($this->lineasDeCombustible(), 'galones');

        return in_array(null, $galones, true) ? null : (float) array_sum($galones);
    }

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentido' => SentidoTramo::class,
            'lineas' => 'array',
        ];
    }
}
