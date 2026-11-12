<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Models\Concerns\RegistraHistorial;
use App\Services\TablaDeTramos;
use Database\Factories\RutaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un tramo estándar de la operación: por dónde va (sus paradas) y cuánto se
 * le da (combustible por punto, viáticos y peajes). Es lo que se elige al
 * programar la ida o la vuelta de un ticket, y lo que abastecimiento ejecuta.
 *
 * Un complemento no define el tramo: se suma a la ruta principal (el
 * metálico al Callao después del concentrado, una carga de paso en Camaná).
 * Si la dotación depende del peso, la ruta tiene variantes (Ransa por
 * toneladas): cada una con sus propias líneas.
 *
 * @phpstan-type ParadaRuta array{zona_origen: string, zona_destino: string|null, tipo_carga: string, lugar_origen?: string|null, lugar_destino?: string|null}
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property SentidoTramo|null $sentido Null: sirve en la ida y en la vuelta.
 * @property bool $es_complemento
 * @property bool $por_tramos Sus galones salen de la tabla de tramos según su recorrido, no se escriben a mano.
 * @property TipoCarga|null $sube_con Un complemento que se suma solo cuando la ida sube a la mina con esta carga (con escoria, +10 gal).
 * @property list<ParadaRuta> $paradas
 * @property bool $pide_destino La carga particular a un destino que se elige al programar.
 * @property int|null $dias_viaje Lo que tarda: de aquí sale la llegada prevista.
 * @property int|null $km_estandar
 * @property string|null $color
 * @property string|null $nota
 * @property bool $activa
 * @property int $orden
 * @property-read Collection<int, LineaRuta> $lineas
 * @property-read Collection<int, VarianteRuta> $variantes
 */
#[Fillable([
    'nombre', 'descripcion', 'sentido', 'es_complemento', 'por_tramos', 'sube_con', 'paradas', 'pide_destino',
    'dias_viaje', 'km_estandar', 'color', 'nota', 'activa', 'orden',
])]
class Ruta extends Model
{
    /** @use HasFactory<RutaFactory> */
    use HasFactory, RegistraHistorial;

    public function etiquetaHistorial(): ?string
    {
        return $this->nombre;
    }

    /**
     * Todas las líneas: las de la ruta y las de sus variantes.
     *
     * @return HasMany<LineaRuta, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaRuta::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * @return HasMany<VarianteRuta, $this>
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(VarianteRuta::class)->orderBy('orden')->orderBy('id');
    }

    public function tieneVariantes(): bool
    {
        return $this->variantes->isNotEmpty();
    }

    /**
     * Si se puede elegir para ese tramo.
     */
    public function sirvePara(SentidoTramo $sentido): bool
    {
        return $this->sentido === null || $this->sentido === $sentido;
    }

    /**
     * Las líneas que tocan con esa variante (o sin ninguna): las comunes más
     * las propias de la variante. Necesita `lineas.punto` cargado.
     *
     * @return list<array{linea_id: int, tipo: string, concepto: string, punto_id: int|null, punto: string|null, galones: float|null, tanque_lleno: bool, monto: float|null}>
     */
    public function lineasPara(?VarianteRuta $variante): array
    {
        $lineas = array_values($this->lineas
            ->filter(fn (LineaRuta $linea): bool => $linea->variante_ruta_id === null || $linea->variante_ruta_id === $variante?->id)
            // Por tramos, el combustible (salvo el full) sale de la tabla.
            ->reject(fn (LineaRuta $linea): bool => $this->por_tramos && $linea->tipo === TipoLineaAbastecimiento::Combustible && ! $linea->tanque_lleno)
            ->map(fn (LineaRuta $linea): array => $linea->copia())
            ->all());

        return $this->por_tramos ? [...app(TablaDeTramos::class)->lineas($this->paradas), ...$lineas] : $lineas;
    }

    /**
     * El tope de combustible con esa variante. Null si alguna línea tiene
     * galones por definir, o si hay variantes y no se eligió ninguna.
     */
    public function topePara(?VarianteRuta $variante): ?float
    {
        if ($variante === null && $this->tieneVariantes()) {
            return null;
        }

        // El tanque full no tiene galones fijos: no arma el tope; lo que dice
        // su voucher descuenta de él.
        $galones = array_column(array_filter(
            $this->lineasPara($variante),
            fn (array $linea): bool => $linea['tipo'] === TipoLineaAbastecimiento::Combustible->value && ! $linea['tanque_lleno'],
        ), 'galones');

        return in_array(null, $galones, true) ? null : (float) array_sum($galones);
    }

    /**
     * La variante que corresponde a ese peso, si la ruta las tiene.
     */
    public function varianteParaPeso(float $toneladas): ?VarianteRuta
    {
        return $this->variantes->first(fn (VarianteRuta $variante): bool => $variante->admitePeso($toneladas));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentido' => SentidoTramo::class,
            'es_complemento' => 'boolean',
            'por_tramos' => 'boolean',
            'sube_con' => TipoCarga::class,
            'paradas' => 'array',
            'pide_destino' => 'boolean',
            'dias_viaje' => 'integer',
            'km_estandar' => 'integer',
            'activa' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
