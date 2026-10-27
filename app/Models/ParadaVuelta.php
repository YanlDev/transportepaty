<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\ParadaVueltaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un trayecto dentro del tramo de ida o de regreso: de dónde a dónde, con qué
 * carga y para quién. Un vacío (por ejemplo, Juliaca → mina después de
 * descargar) es una parada más, sin GR, porque también recorre la ruta.
 *
 * @property int $id
 * @property int $vuelta_id
 * @property SentidoTramo $sentido
 * @property int $orden
 * @property ZonaRuta|null $zona_origen
 * @property ZonaRuta|null $zona_destino
 * @property string|null $lugar_origen
 * @property string|null $lugar_destino
 * @property TipoCarga $tipo_carga
 * @property int|null $cliente_id
 * @property string|null $cliente
 * @property Carbon|null $fecha
 * @property bool $fuera_de_programa Entró por una GR que no calzaba con lo programado.
 * @property-read Vuelta $vuelta
 * @property-read Collection<int, Viaje> $viajes
 */
#[Fillable([
    'vuelta_id', 'sentido', 'orden', 'zona_origen', 'zona_destino', 'lugar_origen',
    'lugar_destino', 'tipo_carga', 'cliente_id', 'cliente', 'fecha', 'fuera_de_programa',
])]
class ParadaVuelta extends Model
{
    /** @use HasFactory<ParadaVueltaFactory> */
    use HasFactory, RegistraHistorial;

    public function etiquetaHistorial(): ?string
    {
        return Vuelta::query()->whereKey($this->vuelta_id)->value('codigo');
    }

    protected $table = 'paradas_vuelta';

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return HasMany<Viaje, $this>
     */
    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }

    public function esVacio(): bool
    {
        return $this->tipo_carga === TipoCarga::Vacio;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentido' => SentidoTramo::class,
            'orden' => 'integer',
            'zona_origen' => ZonaRuta::class,
            'zona_destino' => ZonaRuta::class,
            'tipo_carga' => TipoCarga::class,
            'fecha' => 'date',
            'fuera_de_programa' => 'boolean',
        ];
    }
}
