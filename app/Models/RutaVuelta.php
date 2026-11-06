<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\RutaVueltaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una ruta elegida para un tramo del ticket, copiada como estaba al
 * elegirla: su dotación es lo autorizado. Si el catálogo cambia después,
 * esto no. Un tramo tiene su ruta principal y, si hace falta, complementos.
 *
 * @phpstan-type LineaCopiada array{linea_id: int|null, tipo: string, concepto: string, punto_id: int|null, punto: string|null, galones: float|null, monto: float|null}
 *
 * @property int $id
 * @property int $vuelta_id
 * @property SentidoTramo $sentido
 * @property int|null $ruta_id
 * @property int|null $variante_ruta_id
 * @property bool $es_complemento
 * @property string $nombre
 * @property string|null $variante
 * @property string|null $color
 * @property list<LineaCopiada> $lineas
 * @property bool $variante_pendiente La ruta tiene variantes y todavía no se eligió cuál.
 * @property int|null $dias_viaje
 * @property int|null $km_estandar
 * @property int $orden
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vuelta $vuelta
 * @property-read Ruta|null $ruta
 * @property-read User|null $usuario
 */
#[Fillable([
    'vuelta_id', 'sentido', 'ruta_id', 'variante_ruta_id', 'es_complemento', 'nombre', 'variante',
    'color', 'lineas', 'variante_pendiente', 'dias_viaje', 'km_estandar', 'orden', 'user_id',
])]
class RutaVuelta extends Model
{
    /** @use HasFactory<RutaVueltaFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'rutas_vuelta';

    public function etiquetaHistorial(): ?string
    {
        return Vuelta::query()->whereKey($this->vuelta_id)->value('codigo').' · '.$this->sentido->label().' · '.$this->nombre;
    }

    /**
     * Copia la ruta (con su variante, si se eligió) tal como está ahora.
     * Necesita `lineas.punto` y `variantes` cargados.
     *
     * @return array<string, mixed>
     */
    public static function copiaDe(Ruta $ruta, ?VarianteRuta $variante): array
    {
        return [
            'ruta_id' => $ruta->id,
            'variante_ruta_id' => $variante?->id,
            'es_complemento' => $ruta->es_complemento,
            'nombre' => $ruta->nombre,
            'variante' => $variante?->nombre,
            'color' => $ruta->color,
            'lineas' => $ruta->lineasPara($variante),
            'variante_pendiente' => $variante === null && $ruta->tieneVariantes(),
            'dias_viaje' => $ruta->dias_viaje,
            'km_estandar' => $ruta->km_estandar,
        ];
    }

    /**
     * @return list<LineaCopiada>
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
     * @return list<LineaCopiada>
     */
    public function lineasDeViaticos(): array
    {
        return array_values(array_filter(
            $this->lineas,
            fn (array $linea): bool => $linea['tipo'] !== TipoLineaAbastecimiento::Combustible->value,
        ));
    }

    /**
     * El combustible que aporta esta ruta. Null si falta elegir la variante
     * o alguna línea tiene galones por definir.
     */
    public function tope(): ?float
    {
        if ($this->variante_pendiente) {
            return null;
        }

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
     * @return BelongsTo<Ruta, $this>
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
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
            'es_complemento' => 'boolean',
            'lineas' => 'array',
            'variante_pendiente' => 'boolean',
            'dias_viaje' => 'integer',
            'km_estandar' => 'integer',
            'orden' => 'integer',
        ];
    }
}
