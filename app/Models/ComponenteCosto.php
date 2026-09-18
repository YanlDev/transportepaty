<?php

namespace App\Models;

use App\Enums\MetodoCosto;
use App\Enums\NaturalezaCosto;
use App\Enums\TipoComponente;
use App\Services\Costos\Derivacion;
use Database\Factories\ComponenteCostoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una línea del tarifario: qué se paga, si se paga por día o por kilómetro, y
 * cuánto (la tasa).
 *
 * La tasa se escribe a mano, como en la hoja de cotización de la casa. El
 * método es solo una calculadora de apoyo: arma la cuenta (planilla,
 * depreciación, rendimiento del diésel) y sugiere un número, pero lo que se
 * cotiza es la tasa guardada.
 *
 * @property int $id
 * @property string $nombre
 * @property TipoComponente $tipo
 * @property NaturalezaCosto $naturaleza
 * @property MetodoCosto $metodo
 * @property float $tasa
 * @property array<string, mixed> $entradas
 * @property int $orden
 * @property bool $activo
 */
#[Fillable([
    'nombre',
    'tipo',
    'naturaleza',
    'metodo',
    'tasa',
    'entradas',
    'orden',
    'activo',
])]
class ComponenteCosto extends Model
{
    /** @use HasFactory<ComponenteCostoFactory> */
    use HasFactory;

    protected $table = 'componentes_costo';

    /**
     * La cuenta que arma la calculadora de apoyo y el camino que la explica.
     */
    public function derivacion(ParametroFlota $flota): Derivacion
    {
        return $this->metodo->calculador()->derivar($this->entradas, $flota);
    }

    /**
     * La tasa que sugiere la calculadora, que puede no coincidir con la que se
     * cotiza: esa es `tasa`, y la decide quien arma el tarifario.
     */
    public function tasaCalculada(ParametroFlota $flota): float
    {
        return $this->derivacion($flota)->tasa;
    }

    /**
     * Las líneas de tasa fija no tienen cuenta detrás que mostrar.
     */
    public function tieneCalculadora(): bool
    {
        return $this->metodo !== MetodoCosto::Manual;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoComponente::class,
            'naturaleza' => NaturalezaCosto::class,
            'metodo' => MetodoCosto::class,
            'tasa' => 'float',
            'entradas' => 'array',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
