<?php

namespace App\Models;

use Database\Factories\ProgramacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una unidad programada con carga particular para un día.
 *
 * Se carga antes de que exista la guía de remisión —el viaje se registra
 * después, cuando la GR llega— así que esta tabla y `viajes` no se cruzan:
 * esto es lo que se planificó, aquello lo que se despachó. Que una
 * programación no termine en viaje es un hecho normal del negocio, no un
 * dato inconsistente.
 *
 * Cinco campos y nada más, por pedido expreso: la carga tiene que ser rápida.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $vehiculo_id
 * @property int $conductor_id
 * @property int $cliente_id
 * @property string $destino
 * @property string|null $whatsapp_adicional
 * @property float|null $precio_flete
 * @property bool $precio_incluye_igv
 * @property Carbon|null $aviso_enviado_at
 * @property int|null $aviso_enviado_por
 * @property-read User|null $avisadoPor
 * @property-read Vehiculo $vehiculo
 * @property-read Conductor $conductor
 * @property-read Cliente $cliente
 * @property-read Collection<int, EnvioWhatsapp> $envios
 */
#[Fillable([
    'fecha',
    'vehiculo_id',
    'conductor_id',
    'cliente_id',
    'destino',
    'whatsapp_adicional',
    'precio_flete',
    'precio_incluye_igv',
    'aviso_enviado_at',
    'aviso_enviado_por',
])]
class Programacion extends Model
{
    /** @use HasFactory<ProgramacionFactory> */
    use HasFactory;

    protected $table = 'programaciones';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * Quién le mandó el preaviso al conductor. Null si todavía no se avisó, o
     * si el usuario que avisó ya no existe.
     *
     * @return BelongsTo<User, $this>
     */
    public function avisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aviso_enviado_por');
    }

    /**
     * Los avisos que se mandaron por esta salida desde el número de la
     * empresa, con hasta dónde llegó cada uno.
     *
     * @return HasMany<EnvioWhatsapp, $this>
     */
    public function envios(): HasMany
    {
        return $this->hasMany(EnvioWhatsapp::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeDelDia(Builder $query, string $fecha): void
    {
        $query->where('fecha', $fecha);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'precio_flete' => 'decimal:2',
            'precio_incluye_igv' => 'boolean',
            'aviso_enviado_at' => 'datetime',
        ];
    }
}
