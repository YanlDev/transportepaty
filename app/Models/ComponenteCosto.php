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
 * Una línea de la estructura de costos: qué se paga, si se paga por día o por
 * kilómetro, si es del viaje o de la estructura, y de dónde sale su tasa.
 *
 * Antes esto era un solo número por día y otro por kilómetro. Aplastarlos
 * escondía justo lo que hay que mirar al ponerle precio a un viaje: cuánto del
 * costo se le puede atribuir al viaje y cuánto es estructura que el viaje
 * ayuda a pagar.
 *
 * @property int $id
 * @property string $nombre
 * @property TipoComponente $tipo
 * @property NaturalezaCosto $naturaleza
 * @property MetodoCosto $metodo
 * @property array<string, mixed> $entradas
 * @property int $orden
 * @property bool $activo
 */
#[Fillable([
    'nombre',
    'tipo',
    'naturaleza',
    'metodo',
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
     * La tasa del componente y el camino que la explica.
     */
    public function derivacion(ParametroFlota $flota): Derivacion
    {
        return $this->metodo->calculador()->derivar($this->entradas, $flota);
    }

    /**
     * S/ por día si el componente es fijo, S/ por kilómetro si es variable.
     */
    public function tasa(ParametroFlota $flota): float
    {
        return $this->derivacion($flota)->tasa;
    }

    /**
     * Lo que aporta este componente a un viaje de tantos días y tantos
     * kilómetros.
     */
    public function importe(ParametroFlota $flota, float $km, float $dias): float
    {
        $unidades = match ($this->tipo) {
            TipoComponente::FijoDia => $dias,
            TipoComponente::VariableKm => $km,
        };

        return $this->tasa($flota) * $unidades;
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
            'entradas' => 'array',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
