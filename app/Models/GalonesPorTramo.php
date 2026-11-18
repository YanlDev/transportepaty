<?php

namespace App\Models;

use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Services\TablaDeTramos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que gasta la unidad de una zona a otra, cargada y vacía. Juliaca es la
 * base: un viaje particular es hasta el destino (cargado) y de ahí a
 * Juliaca (vacío, o cargado si trae algo). Null: por definir.
 *
 * Sin carga, la fila vale para cualquiera; con carga (Lima → Mina con
 * particular), manda sobre la general solo cuando el tramo va cargado con
 * eso, y su vacío no se usa.
 *
 * Si cargado gasta distinto según el peso, la fila tiene bandas («más de 5
 * y hasta 8 t: 120 gal») y el número único de cargado no se usa.
 *
 * @phpstan-type Banda array{peso_desde: float|null, peso_hasta: float|null, galones: float}
 *
 * @property int $id
 * @property ZonaRuta $zona_origen
 * @property ZonaRuta $zona_destino
 * @property TipoCarga|null $tipo_carga
 * @property string|null $galones_cargado
 * @property string|null $galones_vacio
 * @property list<Banda>|null $bandas
 * @property string|null $nota
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GalonesPorTramo extends Model
{
    protected $table = 'galones_por_tramo';

    protected $fillable = ['zona_origen', 'zona_destino', 'tipo_carga', 'galones_cargado', 'galones_vacio', 'bandas', 'nota'];

    protected static function booted(): void
    {
        // La tabla se lee una vez por pedido: al cambiarla se vuelve a leer.
        static::saved(fn () => app(TablaDeTramos::class)->olvidar());
        static::deleted(fn () => app(TablaDeTramos::class)->olvidar());
    }

    /**
     * Cómo se busca la fila: «lima>mina>particular», o «lima>mina>» si vale
     * para cualquier carga.
     */
    public static function clave(ZonaRuta|string|null $origen, ZonaRuta|string|null $destino, TipoCarga|string|null $carga = null): string
    {
        $valor = fn (ZonaRuta|TipoCarga|string|null $parte): string => $parte instanceof \BackedEnum ? (string) $parte->value : (string) $parte;

        return $valor($origen).'>'.$valor($destino).'>'.$valor($carga);
    }

    public function tieneBandas(): bool
    {
        return ($this->bandas ?? []) !== [];
    }

    /**
     * Lo que gasta cargado. Con bandas hace falta el peso: sin él, o si
     * ninguna banda lo admite, null (por definir).
     */
    public function galonesCargado(?float $toneladas): ?float
    {
        if (! $this->tieneBandas()) {
            return $this->galones_cargado === null ? null : (float) $this->galones_cargado;
        }

        if ($toneladas === null) {
            return null;
        }

        foreach ($this->bandas as $banda) {
            if (self::admite($banda, $toneladas)) {
                return (float) $banda['galones'];
            }
        }

        return null;
    }

    /**
     * Igual que las variantes de una ruta: más de «desde» y hasta «hasta».
     *
     * @param  array{peso_desde?: float|string|null, peso_hasta?: float|string|null}  $banda
     */
    public static function admite(array $banda, float $toneladas): bool
    {
        return (($banda['peso_desde'] ?? null) === null || $toneladas > (float) $banda['peso_desde'])
            && (($banda['peso_hasta'] ?? null) === null || $toneladas <= (float) $banda['peso_hasta']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zona_origen' => ZonaRuta::class,
            'zona_destino' => ZonaRuta::class,
            'tipo_carga' => TipoCarga::class,
            'galones_cargado' => 'decimal:2',
            'galones_vacio' => 'decimal:2',
            'bandas' => 'array',
        ];
    }
}
